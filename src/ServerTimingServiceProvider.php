<?php

declare(strict_types=1);

namespace Matchory\ServerTiming;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Matchory\ServerTiming\Contracts\ServerTiming as ServerTimingContract;
use Matchory\ServerTiming\Subscribers\{CacheSubscriber, EloquentSubscriber, OctaneSubscriber};
use Symfony\Component\Stopwatch\Stopwatch;

use function implode;

use const DIRECTORY_SEPARATOR as DS;

/**
 * Server Timing Service Provider
 *
 * @bundle Matchory\ServerTiming
 */
final class ServerTimingServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        Event::subscribe(OctaneSubscriber::class);
        Event::subscribe(EloquentSubscriber::class);
        Event::subscribe(CacheSubscriber::class);

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
        }
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            implode(DS, [
                __DIR__,
                'Resources',
                'config',
                'config.php',
            ]) => config_path('timing.php'),
        ], 'server-timing-config');
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(
            implode(DS, [
                __DIR__,
                'Resources',
                'config',
                'config.php',
            ]),
            'timing',
        );

        $this->app->singleton(
            ServerTiming::class,
            fn() => new ServerTiming(new Stopwatch()),
        );
        $this->app->alias(ServerTiming::class, ServerTimingContract::class);
    }
}
