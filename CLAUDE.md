# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Laravel package (`matchory/laravel-server-timing`) that adds `Server-Timing` HTTP headers to responses. Uses Symfony Stopwatch internally. Supports Laravel 10/11/12 and Octane. Requires PHP 8.2+.

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

**`ServerTiming`** — Singleton registered in the container. Wraps Symfony Stopwatch to track named timing events. All durations in milliseconds.

**`ServerTimingMiddleware`** — Measures Bootstrap/App/Total durations, stops all unfinished events, and sets the `Server-Timing` response header.

**`EloquentSubscriber`** — Listens to `QueryExecuted` events. Tracks aggregate DB time per connection (`timing.measure_database`) and individual queries in non-production (`timing.measure_queries`).

**`OctaneSubscriber`** — Resets timing state between requests in Octane's persistent worker model.

**`Facades\ServerTiming`** and **`measure_timing()`** helper — Convenience access to the singleton.

**Config** published to `config/timing.php` with keys: `enabled`, `measure_database`, `measure_queries`.

## Testing

Uses Orchestra Testbench. Tests use `#[Test]` attributes (not `test` prefix naming). Two test files: `ServerTimingTest` (core class) and `ServerTimingMiddlewareTest` (header generation).

## Code Style

PER coding standard via Laravel Pint (`pint.json`). PHPStan at level 8. Strict types throughout. Methods return `$this` for fluent chaining.