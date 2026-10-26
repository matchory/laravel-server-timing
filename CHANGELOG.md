# Changelog

All notable changes to `laravel-server-timing` are documented in this file. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/). See the [upgrade guide](README.md#upgrading-to-v2) for the breaking changes in 2.0.

## 2.0.0 - Unreleased

### Added

- `Contracts\ServerTiming` contract, resolved by the middleware, subscribers, facade and `measure_timing()`.
- `time()` measures a callable and returns its result.
- `describe()` sets a description separate from the metric key.
- `ServerTimingMiddleware::authorize()` decides which requests receive the header; `flushState()` removes the callback.
- `allow_origin` option, sent as the `Timing-Allow-Origin` header.
- `max_queries` option; queries beyond it are summed into an `Other queries` metric.
- Opt-in collectors for cache hits, misses and retrieval time (`measure_cache`), HTTP client requests (`measure_http`) and queued jobs
  (`measure_queue`).
- Opt-in Livewire collector for render and method call time per component (`measure_livewire`).

### Changed

- Requires PHP 8.3 and Laravel 12 or 13.
- The header is only sent outside of production unless an `authorize()` callback allows it.
- All classes are final; the middleware's helper methods are private.
- `measure_timing()` returns the result of the callable.
- `setDuration()` no longer accepts `null`; use `addMetric()`.
- The default database connection is reported as `Database` instead of `Database (name)`.
- `measure_queries` sums repeated queries into one metric with an `(xN)` count.
- Metric names are unique (`-2`, `-3`, ...) and never empty; durations are rounded to three decimals.
- Measured events have 0.1 ms precision instead of whole milliseconds.

### Fixed

- Quotes and backslashes in descriptions are escaped, so queries with quoted identifiers no longer produce an invalid header.
- Total is the elapsed request time, instead of a sum of all events that counted nested events twice.
- The header no longer ends with a trailing separator.
- Events are stopped even if the measured callable throws.
- Any falsy `enabled` value disables the middleware, not only `false`.

### Removed

- Support for PHP 8.2 and Laravel 10 and 11.

## 1.x

See the [GitHub releases](https://github.com/matchory/laravel-server-timing/releases) for changes in earlier versions.
