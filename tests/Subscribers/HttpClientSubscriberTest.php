<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Tests\Subscribers;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Matchory\ServerTiming\Contracts\ServerTiming;
use Matchory\ServerTiming\ServerTimingServiceProvider;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

/**
 * Subscribers read their config in subscribe(), when the provider boots, so
 * the timing config is set with WithConfig's third argument `false`: from
 * testbench 11.3 on it disables deferring until after boot; older versions
 * ignore it and apply the config before boot anyway.
 */
class HttpClientSubscriberTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ServerTimingServiceProvider::class];
    }

    #[Test]
    public function it_records_nothing_by_default(): void
    {
        Http::fake();

        Http::get('https://example.test');

        $this->assertSame([], app(ServerTiming::class)->events());
    }

    #[Test]
    #[WithConfig('timing.enabled', false, false)]
    #[WithConfig('timing.measure_http', true, false)]
    public function it_records_nothing_when_timing_is_disabled(): void
    {
        Http::fake();

        Http::get('https://example.test');

        $this->assertSame([], app(ServerTiming::class)->events());
    }

    #[Test]
    #[WithConfig('timing.measure_http', true, false)]
    public function it_counts_requests_including_pooled_ones(): void
    {
        $timing = app(ServerTiming::class);
        Http::fake();

        Http::get('https://example.test');
        Http::pool(fn(Pool $pool) => [
            $pool->get('https://example.test/a'),
            $pool->get('https://example.test/b'),
        ]);

        $this->assertSame(['HTTP'], array_keys($timing->events()));
        $this->assertSame('HTTP client (3 requests)', $timing->getDescription('HTTP'));
        $this->assertNotNull($timing->getDuration('HTTP'));
    }

    #[Test]
    #[WithConfig('timing.measure_http', true, false)]
    public function it_measures_each_pooled_request_until_it_settles(): void
    {
        $timing = app(ServerTiming::class);
        Http::fake([
            '*/slow' => function () {
                usleep(20_000);

                return Http::response();
            },
            '*/failing' => function () {
                usleep(10_000);

                return Http::failedConnection();
            },
        ]);

        Http::pool(fn(Pool $pool) => [
            $pool->get('https://example.test/slow'),
            $pool->get('https://example.test/failing'),
        ]);

        $this->assertSame('HTTP client (2 requests)', $timing->getDescription('HTTP'));
        $this->assertGreaterThanOrEqual(30.0, $timing->getDuration('HTTP'));
    }

    #[Test]
    #[WithConfig('timing.measure_http', true, false)]
    public function it_measures_requests_that_fail_with_other_exceptions(): void
    {
        $timing = app(ServerTiming::class);
        Http::fake(function () {
            usleep(10_000);

            throw new RuntimeException('Broken middleware');
        });

        try {
            Http::get('https://example.test');
            $this->fail('The request should have failed.');
        } catch (RuntimeException) {
        }

        $this->assertSame('HTTP client (1 request)', $timing->getDescription('HTTP'));
        $this->assertGreaterThanOrEqual(10.0, $timing->getDuration('HTTP'));
    }

    #[Test]
    #[WithConfig('timing.measure_http', true, false)]
    public function it_pairs_real_failed_connections(): void
    {
        $timing = app(ServerTiming::class);
        Http::fake(['*' => Http::failedConnection()]);

        Http::pool(fn(Pool $pool) => [
            $pool->get('https://example.test/a'),
            $pool->post('https://example.test/b'),
        ]);

        $this->assertSame('HTTP client (2 requests)', $timing->getDescription('HTTP'));
        $this->assertGreaterThan(0.0, $timing->getDuration('HTTP'));
    }

    #[Test]
    #[WithConfig('timing.measure_http', true, false)]
    public function it_starts_over_after_a_reset(): void
    {
        $timing = app(ServerTiming::class);
        Http::fake([
            '*/interrupted' => function () use ($timing) {
                usleep(10_000);
                $timing->reset();

                return Http::response();
            },
            '*' => Http::response(),
        ]);

        Http::get('https://example.test/before');
        Http::get('https://example.test/interrupted');
        Http::get('https://example.test/after');

        $this->assertSame('HTTP client (1 request)', $timing->getDescription('HTTP'));
        $this->assertLessThan(10.0, $timing->getDuration('HTTP'));
    }
}
