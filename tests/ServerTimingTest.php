<?php

namespace Matchory\ServerTiming\Tests;

use Matchory\ServerTiming\ServerTiming;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use Symfony\Component\Stopwatch\Stopwatch;

class ServerTimingTest extends TestCase
{
    /**
     * @test
     *
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
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
     * @test
     *
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
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
     * @test
     *
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function it_can_set_durations_with_callables(): void
    {
        $timing = new ServerTiming(new Stopwatch());
        $timing->setDuration('callable', function () {
            sleep(1);
        });

        $events = $timing->events();

        $this->assertCount(1, $events);
        $this->assertArrayHasKey('callable', $events);
        $this->assertTrue($events['callable'] >= 1000.0);
    }

    /**
     * @test
     *
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
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
     * @test
     *
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
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
     * @test
     *
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function it_can_start_and_stop_events(): void
    {
        $timing = new ServerTiming(new Stopwatch());
        $timing->start('key');
        sleep(1);
        $timing->stop('key');

        $events = $timing->events();

        $this->assertCount(1, $events);
        $this->assertArrayHasKey('key', $events);
        $this->assertGreaterThanOrEqual(1000.0, $events['key']);
    }

    /**
     * @test
     *
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function it_can_start_and_stop_events_using_measure(): void
    {
        $timing = new ServerTiming(new Stopwatch());
        $timing->measure('key');
        sleep(1);
        $timing->measure('key');

        $events = $timing->events();

        $this->assertCount(1, $events);
        $this->assertArrayHasKey('key', $events);
        $this->assertGreaterThanOrEqual(1000.0, $events['key']);
    }

    /**
     * @test
     *
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
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
     * @test
     *
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function it_can_be_reset(): void
    {
        $timing = new ServerTiming(new Stopwatch());
        $timing->start('key');
        sleep(1);
        $timing->stop('key');

        $events = $timing->events();

        $this->assertCount(1, $events);
        $this->assertArrayHasKey('key', $events);
        $this->assertGreaterThanOrEqual(1000.0, $events['key']);

        $timing->reset();

        $this->assertCount(0, $timing->events());
    }
}
