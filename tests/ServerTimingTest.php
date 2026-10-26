<?php

namespace Matchory\ServerTiming\Tests;

use Matchory\ServerTiming\ServerTiming;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Stopwatch\Stopwatch;
use TypeError;

class ServerTimingTest extends TestCase
{
    private const int SLEEP_MICROSECONDS = 10_000;

    // Stopwatch rounds timestamps to 0.1 ms, so a 10 ms sleep may measure 9.9 ms.
    private const float MIN_SLEEP_DURATION = 9.9;

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     */
    #[Test]
    public function it_can_set_custom_measures(): void
    {
        $timing = new ServerTiming(new Stopwatch());
        $timing->setDuration('key', 1000);

        $events = $timing->events();

        $this->assertCount(1, $events);
        $this->assertArrayHasKey('key', $events);
        $this->assertSame(1000.0, $events['key']);
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     */
    #[Test]
    public function it_can_set_custom_float_measures(): void
    {
        $timing = new ServerTiming(new Stopwatch());
        $timing->setDuration('key', 1000.123);

        $events = $timing->events();

        $this->assertCount(1, $events);
        $this->assertArrayHasKey('key', $events);
        $this->assertSame(1000.123, $events['key']);
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     */
    #[Test]
    public function it_can_add_to_durations(): void
    {
        $timing = new ServerTiming(new Stopwatch());
        $timing->addDuration('key', 1.5);
        $timing->addDuration('key', 2.25);
        $timing->addMetric('metric');
        $timing->addDuration('metric', 1.0);

        $this->assertSame(['key' => 3.75, 'metric' => 1.0], $timing->events());
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     */
    #[Test]
    public function it_can_set_durations_with_callables(): void
    {
        $timing = new ServerTiming(new Stopwatch());
        $timing->setDuration('callable', function () {
            usleep(self::SLEEP_MICROSECONDS);
        });

        $events = $timing->events();

        $this->assertCount(1, $events);
        $this->assertArrayHasKey('callable', $events);
        $this->assertGreaterThanOrEqual(self::MIN_SLEEP_DURATION, $events['callable']);
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     */
    #[Test]
    public function it_can_set_events_without_duration(): void
    {
        $timing = new ServerTiming(new Stopwatch());
        $timing->addMetric('Custom Metric');

        $events = $timing->events();

        $this->assertCount(1, $events);
        $this->assertArrayHasKey('Custom Metric', $events);
        $this->assertNull($events['Custom Metric']);
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     */
    #[Test]
    public function it_can_set_multiple_events(): void
    {
        $timing = new ServerTiming(new Stopwatch());
        $timing->setDuration('key_1', 1000.0);
        $timing->setDuration('key_2', 2000.0);

        $events = $timing->events();

        $this->assertCount(2, $events);
        $this->assertArrayHasKey('key_1', $events);
        $this->assertArrayHasKey('key_2', $events);

        $this->assertSame(1000.0, $events['key_1']);
        $this->assertSame(2000.0, $events['key_2']);
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     */
    #[Test]
    public function it_can_start_and_stop_events(): void
    {
        $timing = new ServerTiming(new Stopwatch());
        $timing->start('key');
        usleep(self::SLEEP_MICROSECONDS);
        $timing->stop('key');

        $events = $timing->events();

        $this->assertCount(1, $events);
        $this->assertArrayHasKey('key', $events);
        $this->assertGreaterThanOrEqual(self::MIN_SLEEP_DURATION, $events['key']);
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     */
    #[Test]
    public function it_can_start_and_stop_events_using_measure(): void
    {
        $timing = new ServerTiming(new Stopwatch());
        $timing->measure('key');
        usleep(self::SLEEP_MICROSECONDS);
        $timing->measure('key');

        $events = $timing->events();

        $this->assertCount(1, $events);
        $this->assertArrayHasKey('key', $events);
        $this->assertGreaterThanOrEqual(self::MIN_SLEEP_DURATION, $events['key']);
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     */
    #[Test]
    public function it_can_stop_started_events(): void
    {
        $timing = new ServerTiming(new Stopwatch());
        $timing->start('Started');

        $timing->stopAllUnfinishedEvents();
        $events = $timing->events();

        $this->assertCount(1, $events);
        $this->assertArrayHasKey('Started', $events);
        $this->assertNotNull($events['Started']);
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     */
    #[Test]
    public function it_can_be_reset(): void
    {
        $timing = new ServerTiming(new Stopwatch());
        $timing->start('key');
        usleep(self::SLEEP_MICROSECONDS);
        $timing->stop('key');

        $events = $timing->events();

        $this->assertCount(1, $events);
        $this->assertArrayHasKey('key', $events);
        $this->assertGreaterThanOrEqual(self::MIN_SLEEP_DURATION, $events['key']);

        $timing->reset();

        $this->assertCount(0, $timing->events());
    }

    #[Test]
    public function it_stops_callable_events_that_throw(): void
    {
        $timing = new ServerTiming(new Stopwatch());

        try {
            $timing->setDuration('callable', function () {
                throw new RuntimeException('failed');
            });
        } catch (RuntimeException) {
        }

        $this->assertFalse($timing->hasStartedEvent('callable'));
        $this->assertNotNull($timing->getDuration('callable'));
    }

    #[Test]
    public function it_returns_the_result_of_timed_callables(): void
    {
        $timing = new ServerTiming(new Stopwatch());

        $this->assertSame(42, $timing->time('callable', fn() => 42));
        $this->assertFalse($timing->hasStartedEvent('callable'));
        $this->assertNotNull($timing->getDuration('callable'));
    }

    #[Test]
    public function it_stops_timed_callables_that_throw(): void
    {
        $timing = new ServerTiming(new Stopwatch());

        try {
            $timing->time('callable', function () {
                throw new RuntimeException('failed');
            });
        } catch (RuntimeException) {
        }

        $this->assertFalse($timing->hasStartedEvent('callable'));
        $this->assertNotNull($timing->getDuration('callable'));
    }

    #[Test]
    public function it_does_not_accept_null_durations(): void
    {
        $timing = new ServerTiming(new Stopwatch());

        $this->expectException(TypeError::class);

        // @phpstan-ignore argument.type
        $timing->setDuration('key', null);
    }

    #[Test]
    public function it_describes_events(): void
    {
        $timing = new ServerTiming(new Stopwatch());
        $timing->setDuration('db', 1.0);

        $this->assertSame('db', $timing->getDescription('db'));

        $timing->describe('db', 'Database (3 queries)');

        $this->assertSame('Database (3 queries)', $timing->getDescription('db'));

        $timing->reset();

        $this->assertSame('db', $timing->getDescription('db'));
    }
}
