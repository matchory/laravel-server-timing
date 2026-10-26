<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Subscribers;

use Illuminate\Cache\Events\{CacheHit, CacheMissed, RetrievingKey, RetrievingManyKeys};
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use Laravel\Octane\Events\RequestReceived;
use Matchory\ServerTiming\Contracts\ServerTiming;

use function config;
use function hrtime;

/**
 * Counts cache hits and misses, and measures the time spent retrieving them.
 *
 * The subscriber lives for the worker lifetime, so its counters are flushed
 * on every Octane request.
 */
final class CacheSubscriber
{
    private const string KEY = 'Cache';

    private int $hits = 0;

    private int $misses = 0;

    private ?int $retrievalStart = null;

    public function __construct(private readonly ServerTiming $timing) {}

    public function flush(): void
    {
        $this->hits = 0;
        $this->misses = 0;
        $this->retrievalStart = null;
    }

    public function handleRetrieving(): void
    {
        $this->retrievalStart = hrtime(true);
    }

    public function handleHit(): void
    {
        $this->record(hit: true);
    }

    public function handleMiss(): void
    {
        $this->record(hit: false);
    }

    public function subscribe(Dispatcher $events): void
    {
        if (!config('timing.enabled', true) || !config('timing.measure_cache', false)) {
            return;
        }

        $events->listen(
            [RetrievingKey::class, RetrievingManyKeys::class],
            [$this, 'handleRetrieving'],
        );
        $events->listen(CacheHit::class, [$this, 'handleHit']);
        $events->listen(CacheMissed::class, [$this, 'handleMiss']);
        $events->listen(RequestReceived::class, [$this, 'flush']);
    }

    private function record(bool $hit): void
    {
        $duration = $this->timing->getDuration(self::KEY) ?? 0.0;

        $hit ? $this->hits++ : $this->misses++;

        // Multi-key retrievals emit one event per key after a single store
        // call, so only the first event of a retrieval accounts for its time.
        if ($this->retrievalStart !== null) {
            $duration += (hrtime(true) - $this->retrievalStart) / 1e6;
            $this->retrievalStart = null;
        }

        $this->timing
            ->setDuration(self::KEY, $duration)
            ->describe(self::KEY, sprintf(
                'Cache (%d %s, %d %s)',
                $this->hits,
                Str::plural('hit', $this->hits),
                $this->misses,
                Str::plural('miss', $this->misses),
            ));
    }
}
