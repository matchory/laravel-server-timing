<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Tests\Subscribers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Octane\Events\RequestReceived;
use Matchory\ServerTiming\Contracts\ServerTiming;
use Matchory\ServerTiming\ServerTimingServiceProvider;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

use function app;
use function event;

/**
 * Subscribers read their config in subscribe(), when the provider boots, so
 * the timing config is set with WithConfig's third argument `false`: from
 * testbench 11.3 on it disables deferring until after boot; older versions
 * ignore it and apply the config before boot anyway.
 */
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
    #[WithConfig('timing.measure_queries', true, false)]
    public function it_aggregates_repeated_queries(): void
    {
        $this->runQuery(self::SQL, 1.0);
        $this->runQuery(self::SQL, 2.5);
        $this->runQuery(self::SQL, 0.5);
        $this->runQuery('select 1', 3.0);

        $timing = $this->timing();
        $this->assertSame(['Query 1' => 4.0, 'Query 2' => 3.0], $timing->events());
        $this->assertSame(self::SQL . ' (x3)', $timing->getDescription('Query 1'));
        $this->assertSame('select 1', $timing->getDescription('Query 2'));
    }

    #[Test]
    #[WithConfig('timing.measure_queries', true, false)]
    public function it_counts_queries_without_a_time(): void
    {
        $this->runQuery(self::SQL, null);
        $this->runQuery('select 1', null);
        $this->runQuery('select 1', 2.0);
        $this->runQuery('select 1', null);

        $timing = $this->timing();
        $this->assertSame(['Query 1' => null, 'Query 2' => 2.0], $timing->events());
        $this->assertSame(self::SQL, $timing->getDescription('Query 1'));
        $this->assertSame('select 1 (x3)', $timing->getDescription('Query 2'));
    }

    #[Test]
    #[WithConfig('app.env', 'production')]
    #[WithConfig('timing.measure_queries', true, false)]
    public function it_does_not_measure_queries_in_production(): void
    {
        $this->runQuery(self::SQL, 1.0);

        $this->assertSame([], $this->timing()->events());
    }

    #[Test]
    #[WithConfig('timing.measure_queries', true, false)]
    #[WithConfig('timing.max_queries', 2, false)]
    public function it_aggregates_queries_beyond_the_cap(): void
    {
        $this->runQuery('select 1', 1.0);
        $this->runQuery('select 2', 1.0);
        $this->runQuery('select 3', 2.0);
        $this->runQuery('select 1', 1.0);
        $this->runQuery('select 4', 3.0);

        $timing = $this->timing();
        $this->assertSame([
            'Query 1' => 2.0,
            'Query 2' => 1.0,
            'Other queries' => 5.0,
        ], $timing->events());
        $this->assertSame('Other queries (x2)', $timing->getDescription('Other queries'));
    }

    #[Test]
    #[WithConfig('timing.measure_queries', true, false)]
    #[WithConfig('timing.max_queries', 1, false)]
    public function it_starts_counting_afresh_on_each_octane_request(): void
    {
        $this->runQuery('select 1', 1.0);
        $this->runQuery('select 1', 1.0);
        event(new RequestReceived(app(), app(), new Request()));
        $this->runQuery('select 2', 3.0);
        $this->runQuery('select 2', 3.0);

        $timing = $this->timing();
        $this->assertSame(['Query 1' => 6.0], $timing->events());
        $this->assertSame('select 2 (x2)', $timing->getDescription('Query 1'));
    }

    #[Test]
    #[WithConfig('timing.measure_queries', true, false)]
    public function it_cuts_long_queries_short_in_the_description(): void
    {
        $sql = 'select * from users where id in (' . str_repeat('?, ', 100) . '?)';

        $this->runQuery($sql, 1.0);
        $this->runQuery($sql, 1.0);

        $this->assertSame(
            substr($sql, 0, 200) . '… (x2)',
            $this->timing()->getDescription('Query 1'),
        );
    }

    private function runQuery(string $sql, ?float $time, ?string $connection = null): void
    {
        event(new QueryExecuted($sql, [], $time, DB::connection($connection)));
    }

    private function timing(): ServerTiming
    {
        return app(ServerTiming::class);
    }
}
