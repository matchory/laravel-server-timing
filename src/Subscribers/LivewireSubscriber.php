<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Subscribers;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Octane\Events\RequestReceived;
use Livewire\Component;
use Livewire\Livewire;
use Matchory\ServerTiming\Contracts\ServerTiming;

use function class_exists;
use function config;
use function hrtime;

/**
 * Measures the time Livewire components take to render, and the time their
 * methods take to run, summed per component and method.
 *
 * The subscriber lives for the worker lifetime, so its counts are flushed on
 * every Octane request.
 */
final class LivewireSubscriber
{
    /**
     * Number of measurements per metric in the current request.
     *
     * @var array<string, int>
     */
    private array $counts = [];

    public function __construct(private readonly ServerTiming $timing) {}

    public function flush(): void
    {
        $this->counts = [];
    }

    public function subscribe(Dispatcher $events): void
    {
        if (
            !class_exists(Livewire::class)
            || !config('timing.enabled', true)
            || !config('timing.measure_livewire', false)
        ) {
            return;
        }

        // Livewire calls the returned closure once the render or call is done.
        Livewire::listen('render', fn(Component $component) => $this->measure(
            "Livewire: {$component->getName()}",
        ));
        Livewire::listen('call', fn(Component $component, string $method) => $this->measure(
            "Livewire: {$component->getName()}::{$method}",
        ));
        $events->listen(RequestReceived::class, [$this, 'flush']);
    }

    private function measure(string $key): Closure
    {
        $start = hrtime(true);

        return function () use ($key, $start): void {
            $count = $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;

            $this->timing->setDuration(
                $key,
                ($this->timing->getDuration($key) ?? 0.0) + (hrtime(true) - $start) / 1e6,
            );

            if ($count > 1) {
                $this->timing->describe($key, "{$key} (x{$count})");
            }
        };
    }
}
