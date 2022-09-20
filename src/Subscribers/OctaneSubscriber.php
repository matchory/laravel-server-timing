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
