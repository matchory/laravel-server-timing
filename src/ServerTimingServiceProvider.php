<?php

namespace Matchory\ServerTiming;

use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Matchory\ServerTiming\Subscribers\EloquentSubscriber;
use Matchory\ServerTiming\Subscribers\OctaneSubscriber;
use Symfony\Component\Stopwatch\Stopwatch;

use function implode;

use const DIRECTORY_SEPARATOR as DS;

/**
 * ServerTimingServiceProvider
 *
 * @bundle Matchory\ServerTiming
 */
class ServerTimingServiceProvider extends EventServiceProvider
{
    protected $subscribe = [
        OctaneSubscriber::class,
        EloquentSubscriber::class,
    ];

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(
            ServerTiming::class,
            fn() => new ServerTiming(new Stopwatch())
        );
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
}
