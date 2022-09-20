<?php

/**
 * This file is part of laravel-server-timing, a Matchory application.
 *
 * Unauthorized copying of this file, via any medium, is strictly prohibited.
 * Its contents are strictly confidential and proprietary.
 *
 * @copyright 2020–2022 Matchory GmbH · All rights reserved
 * @author    Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ServerTiming\Subscribers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Matchory\ServerTiming\ServerTiming;

use function config;

class EloquentSubscriber
{
    public function __construct(private readonly ServerTiming $timing)
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
