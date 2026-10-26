<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Tests\Subscribers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Matchory\ServerTiming\Contracts\ServerTiming;
use Matchory\ServerTiming\ServerTimingServiceProvider;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

use function event;

#[WithConfig('app.env', 'local')]
#[WithConfig('database.connections.secondary', ['driver' => 'sqlite', 'database' => ':memory:'])]
class EloquentSubscriberTest extends TestCase
{
    private const string SQL = 'select * from users where id = ?';

    protected function getPackageProviders($app): array
    {
        return [ServerTimingServiceProvider::class];
    }

    #[Test]
    public function it_measures_nothing_by_default(): void
    {
        $this->runQuery(self::SQL, 1.0);

        $this->assertSame([], $this->timing()->events());
    }

    #[Test]
    #[WithConfig('timing.enabled', false, false)]
    #[WithConfig('timing.measure_database', true, false)]
    #[WithConfig('timing.measure_queries', true, false)]
    public function it_measures_nothing_when_disabled(): void
    {
        $this->runQuery(self::SQL, 1.0);

        $this->assertSame([], $this->timing()->events());
    }

    #[Test]
    #[WithConfig('timing.measure_database', true, false)]
    public function it_sums_database_time_per_connection(): void
    {
        $this->runQuery(self::SQL, 1.5);
        $this->runQuery(self::SQL, 2.0);
        $this->runQuery(self::SQL, 4.0, 'secondary');
        $this->runQuery(self::SQL, null, 'secondary');

        $this->assertSame([
            'Database' => 3.5,
            'Database (secondary)' => 4.0,
        ], $this->timing()->events());
    }

    #[Test]
    #[WithConfig('app.env', 'production')]
    #[WithConfig('timing.measure_queries', true, false)]
    public function it_does_not_measure_queries_in_production(): void
    {
        $this->runQuery(self::SQL, 1.0);

        $this->assertSame([], $this->timing()->events());
    }

    private function runQuery(string $sql, ?float $time, ?string $connection = null): void
    {
        event(new QueryExecuted($sql, [], $time, DB::connection($connection)));
    }

    private function timing(): ServerTiming
    {
        return $this->app?->make(ServerTiming::class)
            ?? $this->fail('Application not booted');
    }
}
