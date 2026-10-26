<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Subscribers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Laravel\Octane\Events\RequestReceived;
use Matchory\ServerTiming\Contracts\ServerTiming;

use function config;
use function count;

final class EloquentSubscriber
{
    private const string OTHER_QUERIES = 'Other queries';

    /**
     * Number of executions per query metric in the current request.
     *
     * @var array<string, int>
     */
    private array $counts = [];

    public function __construct(private readonly ServerTiming $timing) {}

    public function handleQuery(QueryExecuted $event): void
    {
        $key = $event->sql;
        $distinct = count($this->counts) - (int) isset($this->counts[self::OTHER_QUERIES]);

        if (!isset($this->counts[$key]) && $distinct >= (int) config('timing.max_queries', 20)) {
            $key = self::OTHER_QUERIES;
        }

        $count = $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
        $previous = $this->timing->getDuration($key);

        if ($event->time === null && $previous === null) {
            $this->timing->addMetric($key);
        } else {
            $this->timing->setDuration($key, ($previous ?? 0.0) + ($event->time ?? 0.0));
        }

        if ($count > 1) {
            $this->timing->describe($key, "{$key} (x{$count})");
        }
    }

    /**
     * Clears the per-request counts; this instance outlives Octane requests.
     */
    public function flush(): void
    {
        $this->counts = [];
    }

    public function handleTotal(QueryExecuted $event): void
    {
        $key = 'Database';

        if ($event->connectionName !== config('database.default')) {
            $key .= " ({$event->connectionName})";
        }

        $previous = $this->timing->getDuration($key) ?? 0.0;
        $this->timing->setDuration($key, $previous + $event->time);
    }

    public function subscribe(Dispatcher $events): void
    {
        if (!config('timing.enabled', true)) {
            return;
        }

        if (config('timing.measure_database', false)) {
            $events->listen(
                QueryExecuted::class,
                [self::class, 'handleTotal'],
            );
        }

        if (
            config('timing.measure_queries', false)
            && config('app.env', 'production') !== 'production'
        ) {
            // Bound to this instance, as the per-request counts live here.
            $events->listen(
                QueryExecuted::class,
                [$this, 'handleQuery'],
            );
            $events->listen(
                RequestReceived::class,
                [$this, 'flush'],
            );
        }
    }
}
