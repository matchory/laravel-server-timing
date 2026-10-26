<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Tests\Subscribers;

use GuzzleHttp\Psr7\{Request as PsrRequest, Response as PsrResponse};
use Illuminate\Http\Client\{ConnectionException, Pool, Request, Response};
use Illuminate\Http\Client\Events\{ConnectionFailed, RequestSending, ResponseReceived};
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Octane\Events\RequestReceived;
use Matchory\ServerTiming\Contracts\ServerTiming;
use Matchory\ServerTiming\ServerTimingServiceProvider;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

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
    public function it_pairs_concurrent_requests_with_their_responses(): void
    {
        $timing = app(ServerTiming::class);
        $first = $this->request('https://example.test/first');
        $second = $this->request('https://example.test/second');

        event(new RequestSending($first));
        usleep(10_000);
        event(new RequestSending($second));
        event(new ResponseReceived($second, new Response(new PsrResponse())));
        usleep(10_000);
        event(new ResponseReceived($first, new Response(new PsrResponse())));

        $this->assertSame('HTTP client (2 requests)', $timing->getDescription('HTTP'));
        $this->assertGreaterThanOrEqual(20.0, $timing->getDuration('HTTP'));
        $this->assertLessThan(30.0, $timing->getDuration('HTTP'));
    }

    #[Test]
    #[WithConfig('timing.measure_http', true, false)]
    public function it_measures_failed_connections(): void
    {
        $timing = app(ServerTiming::class);
        $request = $this->request('https://example.test');

        event(new RequestSending($request));
        usleep(10_000);
        event(new ConnectionFailed(
            $this->request('https://example.test'),
            new ConnectionException('Timed out'),
        ));

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
        $pending = $this->request('https://example.test');
        event(new RequestSending($pending));
        event(new RequestSending($this->request('https://example.test/other')));
        usleep(10_000);

        app('events')->dispatch(new RequestReceived(app(), app(), new HttpRequest()));
        event(new RequestSending($this->request('https://example.test/next')));
        event(new ResponseReceived($pending, new Response(new PsrResponse())));

        $this->assertSame('HTTP client (1 request)', $timing->getDescription('HTTP'));
        $this->assertLessThan(10.0, $timing->getDuration('HTTP'));
    }

    private function request(string $url): Request
    {
        return new Request(new PsrRequest('GET', $url));
    }
}
