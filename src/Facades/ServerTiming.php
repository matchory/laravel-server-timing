<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Facades;

use Illuminate\Support\Facades\Facade;
use Matchory\ServerTiming\ServerTiming as Stopwatch;

/**
 * Server Timing Facade
 *
 * @method static float|null getDuration(string $key)
 * @method static float getStart()
 * @method static Stopwatch setDuration(string $key, callable|float|null $duration)
 * @method static Stopwatch addMetric(string $metric)
 * @method static array<string, float|null> events()
 * @method static bool hasStartedEvent(string $key)
 * @method static Stopwatch measure(string $key)
 * @method static Stopwatch start(string $key)
 * @method static Stopwatch stop(string $key)
 * @method static void stopAllUnfinishedEvents()
 * @method static void reset()
 * @bundle Matchory\ServerTiming\Facades
 */
class ServerTiming extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Stopwatch::class;
    }
}
