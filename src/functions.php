<?php

declare(strict_types=1);

use Matchory\ServerTiming\Contracts\ServerTiming;

if (!function_exists('measure_timing')) {
    /**
     * Measures the duration of a callable and returns its result, or toggles
     * the event if no callable is given.
     *
     * @template T
     *
     * @param (callable(): T)|null $callable
     *
     * @return ($callable is null ? null : T)
     */
    function measure_timing(string $key, ?callable $callable = null): mixed
    {
        $timing = app(ServerTiming::class);

        if ($callable === null) {
            $timing->measure($key);

            return null;
        }

        return $timing->time($key, $callable);
    }
}
