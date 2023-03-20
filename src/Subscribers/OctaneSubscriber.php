<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Subscribers;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\WorkerStarting;
use Matchory\ServerTiming\ServerTiming;

class OctaneSubscriber
{
    public function __construct(private readonly ServerTiming $timing)
    {
    }

    public function handle(): void
    {
        $this->timing->reset();
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen([
            WorkerStarting::class,
            RequestReceived::class,
        ], [self::class, 'handle']);
    }
}
