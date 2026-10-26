# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Laravel package (`matchory/laravel-server-timing`) that adds `Server-Timing` HTTP headers to responses. Uses Symfony Stopwatch internally. Supports Laravel 12/13 and Octane. Requires PHP 8.3+.

## Commands

```bash
composer test             # Run tests (PHPUnit)
composer fmt              # Format code (Pint, PER preset)
composer analyze          # Static analysis (PHPStan level 8)
composer test-coverage    # Tests with HTML coverage report
```

Run a single test:
```bash
./vendor/bin/phpunit --filter test_method_name
```

## Architecture

All classes are `final`.

**`Contracts\ServerTiming`** — The public API. Everything (middleware, subscribers, facade, helper) resolves the contract, never the concrete class.

**`ServerTiming`** — Default implementation, registered as a singleton with the contract aliased to it. Wraps Symfony Stopwatch to track named timing events. All durations in milliseconds. `describe()` stores a description separate from the key.

**`ServerTimingMiddleware`** — Measures Bootstrap/App/Total (Total is elapsed request time), stops all unfinished events, and sets the `Server-Timing` header (slugged, de-duplicated names; escaped descriptions; durations rounded to 3 decimals) plus `Timing-Allow-Origin` if `allow_origin` is set. The header is sent outside production only, unless the static `authorize()` callback (run after the app handled the request) says otherwise; `flushState()` clears it. With `context` enabled, it adds the final timings to Laravel's Context as `server_timing` before the authorization check.

**`EloquentSubscriber`** — Listens to `QueryExecuted`. Aggregate DB time per connection (`measure_database`; default connection is `Database`, others `Database (name)`) and per-query metrics in non-production (`measure_queries`; repeats summed with `(xN)`, beyond `max_queries` into `Other queries`).

**`CacheSubscriber`** (`measure_cache`), **`HttpClientSubscriber`** (`measure_http`), **`QueueSubscriber`** (`measure_queue`), **`LivewireSubscriber`** (`measure_livewire`) — Opt-in collectors for cache hits/misses and retrieval time, summed HTTP client request time, the count of queued jobs, and Livewire render/call time per component (via `Livewire::listen()` finishers; no-op without Livewire, which is only suggested).

**`OctaneSubscriber`** — Resets `ServerTiming` on `WorkerStarting` and `RequestReceived`.

**Per-request state** — Subscriber instances outlive Octane requests. Stateful subscribers register their listeners as `[$this, ...]` (so state lives on the subscribed instance) and listen to Octane's `RequestReceived` to `flush()` it. Subscribers read config in `subscribe()`, at boot.

**`Facades\ServerTiming`** and **`measure_timing()`** helper — Convenience access to the singleton; `measure_timing()` returns the callable's result.

**Config** published to `config/timing.php` with keys: `enabled`, `measure_database`, `measure_queries`, `max_queries`, `measure_cache`, `measure_http`, `measure_queue`, `measure_livewire`, `context`, `allow_origin`.

## Testing

Uses Orchestra Testbench. Tests use `#[Test]` attributes (not `test` prefix naming). Core class in `ServerTimingTest`, header generation in `Middleware/ServerTimingMiddlewareTest`, one test file per subscriber in `Subscribers/`, plus `MeasureTimingTest` and `ServerTimingServiceProviderTest`.

Subscriber tests set `timing.*` config with `#[WithConfig('timing.…', value, false)]`: the third argument applies it before the provider boots, which is when subscribers read it. Middleware tests call `ServerTimingMiddleware::flushState()` in teardown.

## Code Style

PER coding standard via Laravel Pint (`pint.json`). PHPStan at level 8. Strict types throughout. Methods return `$this` for fluent chaining.
