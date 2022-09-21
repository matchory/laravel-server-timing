Laravel Server Timings
======================
[![Latest Version on Packagist](https://img.shields.io/packagist/v/matchory/laravel-server-timing.svg?style=flat-square)](https://packagist.org/packages/matchory/laravel-server-timing)
[![Build Status](https://img.shields.io/travis/matchory/laravel-server-timing/master.svg?style=flat-square)](https://travis-ci.org/matchory/laravel-server-timing)
[![Quality Score](https://img.shields.io/scrutinizer/g/matchory/laravel-server-timing.svg?style=flat-square)](https://scrutinizer-ci.com/g/matchory/laravel-server-timing)
[![Total Downloads](https://img.shields.io/packagist/dt/matchory/laravel-server-timing.svg?style=flat-square)](https://packagist.org/packages/matchory/laravel-server-timing)
[![Laravel Octane Compatible](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://github.com/laravel/octane)

> Add Server-Timing header information from within your Laravel apps.

Installation
------------
You can install the package via composer:

```bash
composer require matchory/laravel-server-timing
```

Usage
-----
To add server-timing header information, you need to add the
`\Matchory\ServerTiming\Middleware\ServerTimingMiddleware::class,` middleware to your HTTP Kernel. In order to get the
most accurate results, put the middleware as the first one to load in the middleware stack.

By default, the middleware measures only three things, to keep it as light-weight as possible:

- Bootstrap (time before the middleware gets called)
- Application time (time to get a response within the app)
- Total (total time before sending out the response)

Once the package is successfully installed, you can see your timing information in the developer tools of your browser.
Here's an example from Chrome:
![image](https://user-images.githubusercontent.com/40676515/73973252-d831a980-48e7-11ea-88fc-a606fd5b758a.png)

Enabling automatic database timing
----------------------------------
To enable database timing, you have to options built-in that you can enable via the configuration file:

- `measure_database`: Measure total database timing. This will track the total time spent in database queries.
- `measure_queries`: Measure database timing per query. This will track the time spent in each individual query and add
  them as individual metrics.

> **Note:** If you have many queries, this may result in significantly large headers. Some web servers, like nginx, will
> bail if the headers grow too large. Review the manual for your web server to adjust these limits, if necessary.

Adding additional measurements
------------------------------
If you want to provide additional measurements, you can use the start and stop methods. If you do not explicitly stop a
measured event, the event will automatically be stopped once the middleware receives your response. This can be useful
if you want to measure the time your Blade views take to compile.

```php
ServerTiming::start('Running expensive task');

// do something

ServerTiming::stop('Running expensive task');
```

If you already know the exact time that you want to set as the measured time, you can use the `setDuration` method.
The duration should be set as milliseconds:

```php
ServerTiming::setDuration('Running expensive task', 1_200);
```

In addition to providing milliseconds as the duration, you can also pass a callable that will be measured instead:

```php
ServerTiming::setDuration('Running expensive task', function() {
    sleep(5);
});
```

Adding textual information
--------------------------
You can also use the Server-Timing middleware to only set textual information without providing a duration.

Publishing configuration file
-----------------------------
The configuration file could be published using:
`php artisan vendor:publish --tag=server-timing-config`

You can disable the middleware changing the `timing.enabled` configuration to false.

```php
ServerTiming::addMetric("User: {$user->id}");
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
If you discover any security related issues, please email marcel@beyondco.de instead of using the issue tracker.

### Credits

- [Moritz Friedrich](https://github.com/radiergummi)
- [Marcel Pociot](https://github.com/mpociot)
- [All Contributors](../../contributors)

License
-------
The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
