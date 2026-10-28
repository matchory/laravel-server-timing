<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Tests;

use Matchory\ServerTiming\Contracts\ServerTiming as ServerTimingContract;
use Matchory\ServerTiming\Facades\ServerTiming as ServerTimingFacade;
use Matchory\ServerTiming\Http\Middleware\ServerTimingMiddleware;
use Matchory\ServerTiming\ServerTiming;
use Matchory\ServerTiming\ServerTimingServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Stopwatch\Stopwatch;

class ServerTimingServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ServerTimingServiceProvider::class];
    }

    #[Test]
    public function it_resolves_the_contract_and_the_concrete_to_the_same_singleton(): void
    {
        $contract = $this->app?->make(ServerTimingContract::class);

        $this->assertInstanceOf(ServerTiming::class, $contract);
        $this->assertSame($contract, $this->app?->make(ServerTiming::class));
        $this->assertSame($contract, ServerTimingFacade::getFacadeRoot());
    }

    #[Test]
    public function it_keeps_a_contract_binding_made_before_it_registers(): void
    {
        $custom = new ServerTiming(new Stopwatch());
        $this->app?->singleton(ServerTimingContract::class, fn() => $custom);

        // Registering again stands in for a binding made before registration.
        $this->app?->register(ServerTimingServiceProvider::class, force: true);

        $this->assertSame($custom, $this->app?->make(ServerTimingContract::class));
    }

    #[Test]
    public function it_measures_with_sub_millisecond_precision(): void
    {
        $timing = $this->app?->make(ServerTimingContract::class);
        $durations = [];

        // Any single duration may happen to be a whole number of milliseconds,
        // but with sub-millisecond precision, not all five will.
        for ($i = 0; $i < 5; $i++) {
            $timing?->time("event {$i}", fn() => usleep(1_500));
            $durations[] = $timing?->getDuration("event {$i}");
        }

        $this->assertNotEmpty(array_filter(
            $durations,
            fn(?float $duration) => $duration !== null && floor($duration) !== $duration,
        ));
    }

    #[Test]
    public function it_does_not_carry_timings_over_to_the_next_request(): void
    {
        $this->app?->make('router')
            ->middleware(ServerTimingMiddleware::class)
            ->get('/{metric}', function (string $metric) {
                ServerTimingFacade::setDuration($metric, 1.0);

                return 'ok';
            });

        $this->get('/first');
        $header = (string) $this->get('/second')->headers->get('Server-Timing');

        $this->assertStringContainsString('second', $header);
        $this->assertStringNotContainsString('first', $header);
    }
}
