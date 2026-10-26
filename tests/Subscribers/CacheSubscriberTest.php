<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Tests\Subscribers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Octane\Events\RequestReceived;
use Matchory\ServerTiming\Contracts\ServerTiming;
use Matchory\ServerTiming\ServerTimingServiceProvider;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('cache.default', 'array')]
class CacheSubscriberTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ServerTimingServiceProvider::class];
    }

    #[Test]
    public function it_records_nothing_by_default(): void
    {
        Cache::get('missing');

        $this->assertSame([], app(ServerTiming::class)->events());
    }

    #[Test]
    #[WithConfig('timing.enabled', false, defer: false)]
    #[WithConfig('timing.measure_cache', true, defer: false)]
    public function it_records_nothing_when_timing_is_disabled(): void
    {
        Cache::get('missing');

        $this->assertSame([], app(ServerTiming::class)->events());
    }

    #[Test]
    #[WithConfig('timing.measure_cache', true, defer: false)]
    public function it_counts_hits_and_misses(): void
    {
        $timing = app(ServerTiming::class);
        Cache::put('present', 'value');

        Cache::get('present');
        Cache::get('missing');
        Cache::many(['present', 'other']);

        $this->assertSame(['Cache'], array_keys($timing->events()));
        $this->assertSame('Cache (2 hits, 2 misses)', $timing->getDescription('Cache'));
        $this->assertGreaterThan(0.0, $timing->getDuration('Cache'));
    }

    #[Test]
    #[WithConfig('timing.measure_cache', true, defer: false)]
    public function it_measures_retrieval_time_only(): void
    {
        $timing = app(ServerTiming::class);

        Cache::remember('missing', 60, function () {
            usleep(20_000);

            return 'value';
        });

        $this->assertSame('Cache (0 hits, 1 miss)', $timing->getDescription('Cache'));
        $this->assertLessThan(20.0, $timing->getDuration('Cache'));
    }

    #[Test]
    #[WithConfig('timing.measure_cache', true, defer: false)]
    public function it_starts_over_after_a_reset(): void
    {
        $timing = app(ServerTiming::class);
        Cache::get('missing');
        Cache::get('missing');

        app('events')->dispatch(new RequestReceived(app(), app(), new Request()));
        Cache::get('missing');

        $this->assertSame('Cache (0 hits, 1 miss)', $timing->getDescription('Cache'));
    }
}
