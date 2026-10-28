<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Tests\Subscribers;

use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobQueued;
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
class QueueSubscriberTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ServerTimingServiceProvider::class];
    }

    #[Test]
    public function it_records_nothing_by_default(): void
    {
        $this->queueJob();

        $this->assertSame([], app(ServerTiming::class)->events());
    }

    #[Test]
    #[WithConfig('timing.enabled', false, false)]
    #[WithConfig('timing.measure_queue', true, false)]
    public function it_records_nothing_when_timing_is_disabled(): void
    {
        $this->queueJob();

        $this->assertSame([], app(ServerTiming::class)->events());
    }

    #[Test]
    #[WithConfig('timing.measure_queue', true, false)]
    public function it_counts_queued_jobs(): void
    {
        $timing = app(ServerTiming::class);

        $this->queueJob();
        $this->queueJob();
        $this->queueJob();

        $this->assertSame(['Queue' => null], $timing->events());
        $this->assertSame('Queued jobs (3)', $timing->getDescription('Queue'));
    }

    #[Test]
    #[WithConfig('timing.measure_queue', true, false)]
    public function it_starts_over_after_a_reset(): void
    {
        $timing = app(ServerTiming::class);
        $this->queueJob();
        $this->queueJob();

        app('events')->dispatch(new RequestReceived(app(), app(), new Request()));
        $this->queueJob();

        $this->assertSame('Queued jobs (1)', $timing->getDescription('Queue'));
    }

    private function queueJob(): void
    {
        event(new JobQueued('redis', 'default', 1, 'SomeJob', '{}', null));
    }
}
