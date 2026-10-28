<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Tests\Subscribers;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Laravel\Octane\Events\{RequestReceived, WorkerStarting};
use Matchory\ServerTiming\Contracts\ServerTiming;
use Matchory\ServerTiming\ServerTimingServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\{DataProvider, Test};

class OctaneSubscriberTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ServerTimingServiceProvider::class];
    }

    /**
     * @return array<string, array{callable(Application): object}>
     */
    public static function octaneEvents(): array
    {
        return [
            'request received' => [fn(Application $app) => new RequestReceived($app, $app, new Request())],
            'worker starting' => [fn(Application $app) => new WorkerStarting($app)],
        ];
    }

    /**
     * @param callable(Application): object $event
     */
    #[Test]
    #[DataProvider('octaneEvents')]
    public function it_resets_the_timing_state(callable $event): void
    {
        $app = app();
        $timing = app(ServerTiming::class);
        $timing->setDuration('Previous request', 1.0);
        $timing->start('Unfinished');

        $app['events']->dispatch($event($app));

        $this->assertSame([], $timing->events());
        $this->assertFalse($timing->hasStartedEvent('Unfinished'));
    }
}
