<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Tests;

use Matchory\ServerTiming\Contracts\ServerTiming as ServerTimingContract;
use Matchory\ServerTiming\Facades\ServerTiming as ServerTimingFacade;
use Matchory\ServerTiming\ServerTiming;
use Matchory\ServerTiming\ServerTimingServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

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
}
