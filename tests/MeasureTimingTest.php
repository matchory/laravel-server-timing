<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Tests;

use Matchory\ServerTiming\Contracts\ServerTiming;
use Matchory\ServerTiming\ServerTimingServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

use function measure_timing;

class MeasureTimingTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ServerTimingServiceProvider::class];
    }

    #[Test]
    public function it_returns_the_result_of_the_callable(): void
    {
        $result = measure_timing('Load users', fn() => ['alice', 'bob']);

        $this->assertSame(['alice', 'bob'], $result);
        $this->assertNotNull(app(ServerTiming::class)->getDuration('Load users'));
    }

    #[Test]
    public function it_toggles_the_event_without_a_callable(): void
    {
        $timing = app(ServerTiming::class);

        measure_timing('key');
        $this->assertTrue($timing->hasStartedEvent('key'));

        measure_timing('key');
        $this->assertFalse($timing->hasStartedEvent('key'));
        $this->assertNotNull($timing->getDuration('key'));
    }
}
