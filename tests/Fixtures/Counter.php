<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Tests\Fixtures;

use Livewire\Component;

final class Counter extends Component
{
    public int $count = 0;

    public function increment(): void
    {
        usleep(1_000);
        $this->count++;
    }

    public function render(): string
    {
        return '<div>{{ $count }}</div>';
    }
}
