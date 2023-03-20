<?php

declare(strict_types=1);

namespace Matchory\ServerTiming;

use Symfony\Component\Stopwatch\Stopwatch;

use function defined;
use function microtime;

use const LARAVEL_START;

class ServerTiming
{
    /**
     * @var array<string, float|null>
     */
    private array $finishedEvents = [];

    private float $start = 0.0;

    /**
     * @var array<string, bool>
     */
    private array $startedEvents = [];

    public function __construct(private readonly Stopwatch $stopwatch)
    {
        $this->reset();
    }

    /**
     * Retrieves the duration of an event.
     *
     * @param string $key
     *
     * @return float|null
     */
    public function getDuration(string $key): float|null
    {
        return $this->finishedEvents[$key] ?? null;
    }

    /**
     * Retrieves the current starting time.
     *
     * @return float|int
     */
    public function getStart(): float|int
    {
        return $this->start;
    }

    public function setDuration(
        string $key,
        callable|float|null $duration
    ): static {
        if (is_callable($duration)) {
            $this->start($key);
            $duration();
            $this->stop($key);
        } else {
            $this->finishedEvents[$key] = $duration;
        }

        return $this;
    }

    public function addMetric(string $metric): static
    {
        $this->finishedEvents[$metric] = null;

        return $this;
    }

    /**
     * @return array<string, float|null>
     */
    public function events(): array
    {
        return $this->finishedEvents;
    }

    public function hasStartedEvent(string $key): bool
    {
        return array_key_exists($key, $this->startedEvents);
    }

    public function measure(string $key): static
    {
        if ( ! $this->hasStartedEvent($key)) {
            return $this->start($key);
        }

        return $this->stop($key);
    }

    public function reset(): void
    {
        $this->finishedEvents = [];
        $this->startedEvents = [];
        $this->start = $this->resolveRequestStartTime();
        $this->stopwatch->reset();
    }

    public function start(string $key): static
    {
        $this->stopwatch->start($key);

        $this->startedEvents[$key] = true;

        return $this;
    }

    public function stop(string $key): static
    {
        if ($this->stopwatch->isStarted($key)) {
            $event = $this->stopwatch->stop($key);

            $this->setDuration($key, (float)$event->getDuration());

            unset($this->startedEvents[$key]);
        }

        return $this;
    }

    public function stopAllUnfinishedEvents(): void
    {
        foreach (array_keys($this->startedEvents) as $startedEventName) {
            $this->stop($startedEventName);
        }
    }

    /**
     * Retrieves the start timestamp of the current request.
     *
     * @return float|int
     * @noinspection UnnecessaryCastingInspection
     */
    private function resolveRequestStartTime(): float|int
    {
        if (isset($_SERVER['LARAVEL_OCTANE'])) {
            return microtime(true);
        }

        if (defined('LARAVEL_START')) {
            return (int)LARAVEL_START;
        }

        return $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
    }
}
