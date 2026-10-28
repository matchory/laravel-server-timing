<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Subscribers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;
use Matchory\ServerTiming\Contracts\ServerTiming;

use function config;
use function count;

final class EloquentSubscriber
{
    private const string OTHER_QUERIES = 'Other queries';

    private const int MAX_SQL_LENGTH = 200;

    /**
     * Metric key per distinct query in the current request.
     *
     * @var array<string, string>
     */
    private array $keys = [];

    /**
     * Number of executions per query metric in the current request.
     *
     * @var array<string, int>
     */
    private array $counts = [];

    private int $maxQueries = 20;

    public function __construct(private readonly ServerTiming $timing) {}

    public function handleQuery(QueryExecuted $event): void
    {
        $key = $this->keys[$event->sql] ?? null;

        if ($key === null && count($this->keys) < $this->maxQueries) {
            $key = 'Query ' . (count($this->keys) + 1);
            $this->keys[$event->sql] = $key;
        }

        $key ??= self::OTHER_QUERIES;
        $count = $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;

        if ($event->time === null && $this->timing->getDuration($key) === null) {
            $this->timing->addMetric($key);
        } else {
            $this->timing->addDuration($key, $event->time ?? 0.0);
        }

        // The SQL is only sent as the description, cut short to keep the
        // header within web server limits.
        $description = $key === self::OTHER_QUERIES
            ? $key
            : Str::limit($event->sql, self::MAX_SQL_LENGTH, '…');

        $this->timing->describe($key, $count > 1 ? "{$description} (x{$count})" : $description);
    }

    /**
     * Clears the per-request state; this instance outlives Octane requests,
     * so it runs whenever the timings are reset.
     */
    public function flush(): void
    {
        $this->keys = [];
        $this->counts = [];
    }

    public function handleTotal(QueryExecuted $event): void
    {
        $key = 'Database';

        // Read per query rather than at boot, as apps may switch the default
        // connection during a request (e.g. for tenancy).
        if ($event->connectionName !== config('database.default')) {
            $key .= " ({$event->connectionName})";
        }

        $this->timing->addDuration($key, $event->time ?? 0.0);
    }

    public function subscribe(Dispatcher $events): void
    {
        if (!config('timing.enabled', true)) {
            return;
        }

        if (config('timing.measure_database', false)) {
            $events->listen(
                QueryExecuted::class,
                [$this, 'handleTotal'],
            );
        }

        if (
            config('timing.measure_queries', false)
            && !app()->isProduction()
        ) {
            $this->maxQueries = (int) config('timing.max_queries', 20);

            // Bound to this instance, as the per-request counts live here.
            $events->listen(
                QueryExecuted::class,
                [$this, 'handleQuery'],
            );
            $this->timing->onReset($this->flush(...));
        }
    }
}
