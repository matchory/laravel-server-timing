<?php

namespace Matchory\ServerTiming\Facades;

use Illuminate\Support\Facades\Facade;
use Matchory\ServerTiming\ServerTiming as Stopwatch;

/**
 * Server Timing Facade
 *
 * @method static float|null getDuration(string $key)
 * @method static Stopwatch setDuration(string $key, callable|float $duration)
 * @method static Stopwatch addMetric(string $metric)
 * @method static float[] events()
 * @method static bool hasStartedEvent(string $key)
 * @method static Stopwatch measure(string $key)
 * @method static Stopwatch start(string $key)
 * @method static Stopwatch stop(string $key)
 * @method static void stopAllUnfinishedEvents()
 * @bundle Matchory\ServerTiming\Facades
 */
class ServerTiming extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Stopwatch::class;
    }
}
