<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Tests\Subscribers;

use Illuminate\Http\Request;
use Laravel\Octane\Events\RequestReceived;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Matchory\ServerTiming\Contracts\ServerTiming;
use Matchory\ServerTiming\ServerTimingServiceProvider;
use Matchory\ServerTiming\Tests\Fixtures\Counter;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Subscribers read their config in subscribe(), when the provider boots, so
 * the timing config is set with WithConfig's third argument `false`: from
 * testbench 11.3 on it disables deferring until after boot; older versions
 * ignore it and apply the config before boot anyway.
 */
#[WithConfig('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=')]
class LivewireSubscriberTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class, ServerTimingServiceProvider::class];
    }

    #[Test]
    public function it_records_nothing_by_default(): void
    {
        Livewire::test(Counter::class)->call('increment');

        $this->assertSame([], app(ServerTiming::class)->events());
    }

    #[Test]
    #[WithConfig('timing.enabled', false, false)]
    #[WithConfig('timing.measure_livewire', true, false)]
    public function it_records_nothing_when_timing_is_disabled(): void
    {
        Livewire::test(Counter::class)->call('increment');

        $this->assertSame([], app(ServerTiming::class)->events());
    }

    #[Test]
    #[WithConfig('timing.measure_livewire', true, false)]
    public function it_measures_renders_and_calls_per_component(): void
    {
        $timing = app(ServerTiming::class);
        $name = Livewire::test(Counter::class)->call('increment')->instance()->getName();

        $render = "Livewire: {$name}";
        $call = "Livewire: {$name}::increment";

        $this->assertSame([$render, $call], array_keys($timing->events()));
        $this->assertGreaterThan(0.0, $timing->getDuration($render));
        $this->assertGreaterThanOrEqual(1.0, $timing->getDuration($call));
        // Mounting and the call each render the component once.
        $this->assertSame("{$render} (x2)", $timing->getDescription($render));
        $this->assertSame($call, $timing->getDescription($call));
    }

    #[Test]
    #[WithConfig('timing.measure_livewire', true, false)]
    public function it_starts_over_after_a_reset(): void
    {
        $timing = app(ServerTiming::class);
        $name = Livewire::test(Counter::class)->instance()->getName();
        Livewire::test(Counter::class);

        app('events')->dispatch(new RequestReceived(app(), app(), new Request()));
        $timing->reset();
        Livewire::test(Counter::class);

        $this->assertSame("Livewire: {$name}", $timing->getDescription("Livewire: {$name}"));
    }
}
