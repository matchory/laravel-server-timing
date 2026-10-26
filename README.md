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
request, so session and authentication state are available, and `$request->user()` works.

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

If you already know the duration, set it with `setDuration()`. It also accepts a callable, which is measured like with `time()`; then it
returns the timing instance instead of the callable's result.

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

Cache, HTTP client and queue
----------------------------
Three more collectors are available, each disabled by default:

- `measure_cache`: Adds a `Cache` metric with the number of hits and misses, like `Cache (2 hits, 1 miss)`, and the time spent retrieving keys
  from the cache store. Only retrieval is timed: writes, and the callback of `remember()` on a miss, are not included.
- `measure_http`: Adds an `HTTP` metric with the number of requests sent through Laravel's HTTP client, like `HTTP client (3 requests)`, and the
  sum of their durations. Concurrent requests, like those in a pool, each count with their full duration, so the sum can exceed the wall-clock
  time.
- `measure_queue`: Adds a `Queue` metric with the number of jobs pushed to a queue, like `Queued jobs (2)`, without a duration. Jobs run by the
  `sync` driver are not counted; their time is part of App.

There is no collector for views, as Laravel has no event for the time it takes to render one. Measure rendering yourself, if you need to:

```php
$html = ServerTiming::time('Render', fn () => view('dashboard')->render());
```

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
