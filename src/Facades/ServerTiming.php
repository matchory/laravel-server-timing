<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Facades;

use Illuminate\Support\Facades\Facade;
use Matchory\ServerTiming\Contracts\ServerTiming as ServerTimingContract;

/**
 * Server Timing Facade
 *
 * @method static float|null getDuration(string $key)
 * @method static float getStart()
 * @method static ServerTimingContract setDuration(string $key, callable|float $duration)
 * @method static ServerTimingContract addDuration(string $key, float $duration)
 * @method static mixed time(string $key, callable $callback)
 * @method static ServerTimingContract addMetric(string $metric)
 * @method static ServerTimingContract describe(string $key, string $description)
 * @method static string getDescription(string $key)
 * @method static array<string, float|null> events()
 * @method static bool hasStartedEvent(string $key)
 * @method static ServerTimingContract measure(string $key)
 * @method static ServerTimingContract start(string $key)
 * @method static ServerTimingContract stop(string $key)
 * @method static void stopAllUnfinishedEvents()
 * @method static void reset()
 * @bundle Matchory\ServerTiming\Facades
 */
final class ServerTiming extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ServerTimingContract::class;
    }
}
