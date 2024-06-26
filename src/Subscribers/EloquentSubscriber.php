<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Subscribers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Matchory\ServerTiming\ServerTiming;

use function config;

readonly class EloquentSubscriber
{
    public function __construct(private ServerTiming $timing)
    {
    }

    public function handleQuery(QueryExecuted $event): void
    {
        $this->timing->setDuration($event->sql, $event->time);
    }

    public function handleTotal(QueryExecuted $event): void
    {
        $key = 'Database';

        if ($event->connectionName && $event->connectionName !== 'default') {
            $key .= " ({$event->connectionName})";
        }

        $previous = $this->timing->getDuration($key) ?? 0.0;
        $this->timing->setDuration($key, $previous + $event->time);
    }

    public function subscribe(Dispatcher $events): void
    {
        if ( ! config('timing.enabled', true)) {
            return;
        }

        if (config('timing.measure_database', true)) {
            $events->listen(
                QueryExecuted::class,
                [self::class, 'handleTotal']
            );
        }

        if (
            config('timing.measure_queries', false) &&
            config('app.env', 'production') !== 'production'
        ) {
            $events->listen(
                QueryExecuted::class,
                [self::class, 'handleQuery']
            );
        }
    }
}
