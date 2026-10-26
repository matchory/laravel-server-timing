<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Subscribers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobQueued;
use Laravel\Octane\Events\RequestReceived;
use Matchory\ServerTiming\Contracts\ServerTiming;

use function config;

/**
 * Counts the jobs pushed to a queue.
 *
 * The subscriber lives for the worker lifetime, so its counter is flushed on
 * every Octane request.
 */
final class QueueSubscriber
{
    private const string KEY = 'Queue';

    private int $count = 0;

    public function __construct(private readonly ServerTiming $timing) {}

    public function flush(): void
    {
        $this->count = 0;
    }

    public function handleQueued(): void
    {
        $this->count++;

        $this->timing
            ->addMetric(self::KEY)
            ->describe(self::KEY, "Queued jobs ({$this->count})");
    }

    public function subscribe(Dispatcher $events): void
    {
        if (!config('timing.enabled', true) || !config('timing.measure_queue', false)) {
            return;
        }

        $events->listen(JobQueued::class, [$this, 'handleQueued']);
        $events->listen(RequestReceived::class, [$this, 'flush']);
    }
}
