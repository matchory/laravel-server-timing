<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Contracts;

interface ServerTiming
{
    /**
     * Retrieves the duration of an event in milliseconds.
     */
    public function getDuration(string $key): ?float;

    /**
     * Retrieves the start timestamp of the current request.
     */
    public function getStart(): float;

    /**
     * Sets the duration of an event in milliseconds, or measures the
     * duration of a callable.
     */
    public function setDuration(string $key, callable|float $duration): static;

    /**
     * Measures the duration of a callable and returns its result. The event
     * is stopped even if the callable throws.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function time(string $key, callable $callback): mixed;

    /**
     * Adds a metric without a duration.
     */
    public function addMetric(string $metric): static;

    /**
     * Sets the human-readable description of an event. The key remains the
     * metric name; without a description, the key is used as description.
     */
    public function describe(string $key, string $description): static;

    public function getDescription(string $key): string;

    /**
     * @return array<string, float|null>
     */
    public function events(): array;

    public function hasStartedEvent(string $key): bool;

    /**
     * Starts the event if it has not been started yet, stops it otherwise.
     */
    public function measure(string $key): static;

    public function reset(): void;

    public function start(string $key): static;

    public function stop(string $key): static;

    public function stopAllUnfinishedEvents(): void;
}
