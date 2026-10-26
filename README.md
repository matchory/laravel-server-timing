Laravel Server Timings
======================
[![Latest Version on Packagist](https://img.shields.io/packagist/v/matchory/laravel-server-timing.svg?style=flat)](https://packagist.org/packages/matchory/laravel-server-timing)
[![Build Status](https://github.com/matchory/laravel-server-timing/actions/workflows/ci.yml/badge.svg)](https://github.com/matchory/laravel-server-timing/actions/workflows/ci.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/matchory/laravel-server-timing.svg?style=flat)](https://packagist.org/packages/matchory/laravel-server-timing)
[![Laravel Octane Compatible](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://github.com/laravel/octane)

> Add Server-Timing header information from within your Laravel apps.

Requirements
------------
- PHP 8.3 or newer
- Laravel 12 or 13

Installation
------------
You can install the package via composer:

```bash
composer require matchory/laravel-server-timing
```

Usage
-----
Register the middleware in `bootstrap/app.php`. Prepend it to the global middleware stack, so it runs first and the Bootstrap and App timings
are accurate:

```php
use Illuminate\Foundation\Configuration\Middleware;
use Matchory\ServerTiming\Http\Middleware\ServerTimingMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    // ...
    ->withMiddleware(fn (Middleware $middleware) => $middleware->prepend(ServerTimingMiddleware::class))
    ->create();
```

By default, the middleware measures only three things, to keep it as light-weight as possible:

- **Bootstrap:** the time from the start of the request until the middleware is called
- **App:** the time the application takes to return a response
- **Total:** the time from the start of the request until the response leaves the middleware

Once the package is installed, you can see your timing information in the developer tools of your browser. Here's an example from Chrome:
![image](https://user-images.githubusercontent.com/40676515/73973252-d831a980-48e7-11ea-88fc-a606fd5b758a.png)

Production visibility
---------------------
Timings reveal details about your application, so the header is only sent outside of production by default. To decide yourself which requests
receive it, register a callback, for example in the `boot()` method of a service provider:

```php
use Illuminate\Http\Request;
use Matchory\ServerTiming\Http\Middleware\ServerTimingMiddleware;

ServerTimingMiddleware::authorize(
    fn (Request $request) => app()->isLocal() || $request->user()?->is_admin,
);
```

The callback replaces the default check in every environment, and its result is cast to `bool`. It runs after the application handled the
request, so session and authentication state, like `$request->user()`, are available.

The callback is stored statically. If you register one in a test, remove it afterwards with `ServerTimingMiddleware::flushState()`.

Adding measurements
-------------------
Use the facade (`Matchory\ServerTiming\Facades\ServerTiming`), the `measure_timing()` helper, or inject the contract (see
[Dependency injection](#dependency-injection)). All durations are in milliseconds.

To measure a callable, use `time()`. It returns the result of the callable, and stops the event even if the callable throws:

```php
use Matchory\ServerTiming\Facades\ServerTiming;

$users = ServerTiming::time('Load users', fn () => User::all());
```

The `measure_timing()` helper does the same:

```php
$users = measure_timing('Load users', fn () => User::all());
```

To measure a section of code, use `start()` and `stop()`. Events that are still running when the response reaches the middleware are stopped
automatically.

```php
ServerTiming::start('Running expensive task');

// do something

ServerTiming::stop('Running expensive task');
```

`measure()` starts an event if it is not running, and stops it otherwise. `measure_timing()` without a callable does the same, and returns
`null`:

```php
measure_timing('Running expensive task');

// do something

measure_timing('Running expensive task');
```

If you already know the duration, set it with `setDuration()`. It also accepts a callable to measure, but unlike `time()`, it returns the
timing instance instead of the result.

```php
ServerTiming::setDuration('Running expensive task', 1_200);
```

To add a metric without a duration, use `addMetric()`:

```php
ServerTiming::addMetric("User: {$user->id}");
```

Metric names
------------
The key of an event is turned into the metric name with `Str::slug()` and is sent as its description, too: `Load users` becomes
`load-users;desc="Load users"`. To use a short name with a longer description, use `describe()`:

```php
ServerTiming::describe('search', 'Search index lookup')
    ->time('search', fn () => $index->search($query));
```

Browsers merge or drop metrics with the same name, so names are unique: if two keys slug to the same name, the second one gets `-2`, the third
`-3`, and so on. A key that slugs to an empty name is sent as `metric`. Durations are rounded to three decimals.

Database timing
---------------
There are two built-in options that you can enable via the configuration:

- `measure_database`: Measures the total time spent in database queries, per connection. The default connection is reported as `Database`,
  other connections as `Database (name)`.
- `measure_queries`: Measures the time spent in each query, as individual metrics. Repeated queries are summed into one metric, and their
  description shows the count, like `select * from users where id = ? (x3)`. Beyond `max_queries` distinct queries (20 by default), the
  remaining ones are summed into a single `Other queries` metric. Queries are never measured individually if `app.env` is `production`, even if
  the request is authorized.

> **Note:** The header grows with every metric. Some web servers, like nginx, reject responses with large headers (often beyond 4 or 8 KB).
> Lower `max_queries`, or adjust the limits of your web server, if necessary.

Cache, HTTP client, queue and Livewire
--------------------------------------
Four more collectors are available, each disabled by default:

- `measure_cache`: Adds a `Cache` metric with the number of hits and misses, like `Cache (2 hits, 1 miss)`, and the time spent retrieving keys
  from the cache store. Only retrieval is timed: writes, and the callback of `remember()` on a miss, are not included.
- `measure_http`: Adds an `HTTP` metric with the number of requests sent through Laravel's HTTP client, like `HTTP client (3 requests)`, and the
  sum of their durations. Concurrent requests, like those in a pool, each count with their full duration, so the sum can exceed the wall-clock
  time.
- `measure_queue`: Adds a `Queue` metric with the number of jobs pushed to a queue, like `Queued jobs (2)`, without a duration. Jobs run by the
  `sync` driver are not counted; their time is part of App.

- `measure_livewire`: Requires [Livewire](https://livewire.laravel.com) 3 or 4. Adds a metric per component with the time it took to render,
  like `Livewire: counter`, and one per called method, like `Livewire: counter::increment`. Repeated renders and calls are summed and show
  their count, like `Livewire: counter (x2)`. A component's render time includes the components nested in it.

There is no collector for plain views, as Laravel has no event for the time it takes to render one. Measure rendering yourself, if you need to:

```php
$html = ServerTiming::time('Render', fn () => view('dashboard')->render());
```

Timings in logs
---------------
To include the timings in your logs, enable the `context` option. At the end of the request, the middleware adds the final timings to
Laravel's [Context](https://laravel.com/docs/context) as `server_timing`, a map of metric keys to durations in milliseconds:

```json
{"server_timing": {"Bootstrap": 12.4, "Database": 31.02, "App": 88.1, "Total": 100.5}}
```

Context is added to log entries when they are written, so only entries written after the request was measured include the timings: for
example, those written in `defer()` callbacks or terminating callbacks. The timings are added whether or not the request receives the header,
as they stay on the server.

Configuration
-------------
To publish the configuration file to `config/timing.php`, run:

```bash
php artisan vendor:publish --tag=server-timing-config
```

| Key                | Environment variable             | Default | Description                                                  |
|--------------------|----------------------------------|---------|--------------------------------------------------------------|
| `enabled`          | `SERVER_TIMING_ENABLED`          | `true`  | If falsy, the middleware does nothing and collectors are off |
| `measure_database` | `SERVER_TIMING_MEASURE_DATABASE` | `false` | Total database time per connection                           |
| `measure_queries`  | `SERVER_TIMING_MEASURE_QUERIES`  | `false` | Time per query, outside of production only                   |
| `max_queries`      | `SERVER_TIMING_MAX_QUERIES`      | `20`    | Distinct queries before the rest go into `Other queries`     |
| `measure_cache`    | `SERVER_TIMING_MEASURE_CACHE`    | `false` | Cache hits, misses and retrieval time                        |
| `measure_http`     | `SERVER_TIMING_MEASURE_HTTP`     | `false` | HTTP client requests and the sum of their durations          |
| `measure_queue`    | `SERVER_TIMING_MEASURE_QUEUE`    | `false` | Number of queued jobs                                        |
| `measure_livewire` | `SERVER_TIMING_MEASURE_LIVEWIRE` | `false` | Livewire render and method call time per component           |
| `context`          | `SERVER_TIMING_CONTEXT`          | `false` | Add the final timings to Laravel's Context for logs          |
| `allow_origin`     | `SERVER_TIMING_ALLOW_ORIGIN`     | `null`  | Value of the `Timing-Allow-Origin` header                    |

The collectors read their configuration when the application boots.

Browsers only expose server timings to scripts on other origins if the response allows it with a `Timing-Allow-Origin` header. Set
`allow_origin` to `*` or to an origin like `https://example.com` to send it along with the `Server-Timing` header.

Octane
------
The package works with [Laravel Octane](https://github.com/laravel/octane). All timings and collector state are reset when a request is received,
so nothing leaks between requests. As the worker is already booted, Bootstrap is close to zero under Octane.

Dependency injection
--------------------
To use the timings without the facade, type-hint the contract, `Matchory\ServerTiming\Contracts\ServerTiming`:

```php
use Matchory\ServerTiming\Contracts\ServerTiming;

public function __construct(private readonly ServerTiming $timing) {}
```

To swap the implementation, bind the contract in a service provider. The middleware, the collectors, the facade and `measure_timing()` all
resolve the contract:

```php
use Matchory\ServerTiming\Contracts\ServerTiming;

$this->app->singleton(ServerTiming::class, MyServerTiming::class);
```

Upgrading to v2
---------------
Version 2 contains the following breaking changes:

- **Requirements:** PHP `^8.3`; Laravel 12 or 13. Support for Laravel 10 and 11 was dropped.
- **Header in production:** the `Server-Timing` header is no longer sent in production by default. Register a callback with
  `ServerTimingMiddleware::authorize()` (see [Production visibility](#production-visibility)). To restore the old behaviour, send it to everyone:
  `ServerTimingMiddleware::authorize(fn () => true)`.
- **`setDuration()`:** passing `null` as the duration throws a `TypeError`. Use `addMetric($key)` for metrics without a duration.
- **Final classes:** all classes are `final`, and the formerly protected methods of `ServerTimingMiddleware` are private. Compose the classes
  instead of extending them.
- **Container bindings:** the package resolves `Contracts\ServerTiming` instead of the concrete class. To swap the implementation, implement
  the contract and bind it (see [Dependency injection](#dependency-injection)).
- **`measure_timing()`:** returns the result of the callable, or `null` without one, instead of `void`.
- **Database metric:** the default connection is reported as `Database` instead of `Database (mysql)`. Other connections keep their name.
- **`measure_queries`:** repeated queries are summed into one metric with an `(xN)` count, and beyond `max_queries` distinct queries, the rest
  go into `Other queries`. Before, a repeated query showed only its last execution.
- **Total:** Total is the elapsed time of the request, instead of the sum of all events. Nested events, like database time, are no longer
  counted twice.
- **Metric names:** metrics whose keys slug to the same name are suffixed with `-2`, `-3`, and so on, instead of sharing a name. Durations are
  rounded to three decimals.
- **Published configuration:** if you published `config/timing.php`, the new keys `max_queries`, `measure_cache`, `measure_http`,
  `measure_queue`, `measure_livewire`, `context` and `allow_origin` fall back to the package defaults. Copy them from the package configuration to change them in the file.

Changelog
---------
Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

Contributing
------------
Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Testing
To run unit tests, use the following command:

``` bash
composer test
```

You can also run the type checks using the following command:

``` bash
composer analyze
```

### Security
If you discover a security vulnerability, please
[report it privately on GitHub](https://github.com/matchory/laravel-server-timing/security/advisories/new) instead of using the issue tracker.

### Credits

- [Moritz Friedrich](https://github.com/radiergummi)
- [Marcel Pociot](https://github.com/mpociot)
- [All Contributors](../../contributors)

License
-------
The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
