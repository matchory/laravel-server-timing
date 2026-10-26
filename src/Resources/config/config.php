<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel Server Timing enabled
    |--------------------------------------------------------------------------
    |
    | This configuration is used to enable the server timing measurement,
    | if set to false, the middleware will be bypassed
    |
    */
    'enabled' => env('SERVER_TIMING_ENABLED', true),

    /*
     |--------------------------------------------------------------------------
     | Measure total database timing
     |--------------------------------------------------------------------------
     |
     | This setting, if enabled, will measure the total time spent in database
     | queries, per connection. This value should generally safe to print
     | in production.
     */
    'measure_database' => env('SERVER_TIMING_MEASURE_DATABASE', false),

    /*
     |--------------------------------------------------------------------------
     | Measure individual query timing
     |--------------------------------------------------------------------------
     |
     | This setting, if enabled, will measure the time spent for every query
     | executed by the database, and add the timing as separate values
     */
    'measure_queries' => env('SERVER_TIMING_MEASURE_QUERIES', false),

    /*
     |--------------------------------------------------------------------------
     | Measure cache usage
     |--------------------------------------------------------------------------
     |
     | This setting, if enabled, will count cache hits and misses, and measure
     | the total time spent retrieving them from the cache store.
     */
    'measure_cache' => env('SERVER_TIMING_MEASURE_CACHE', false),

    /*
     |--------------------------------------------------------------------------
     | Measure HTTP client requests
     |--------------------------------------------------------------------------
     |
     | This setting, if enabled, will count outgoing requests made with the
     | HTTP client, and measure the sum of their durations.
     */
    'measure_http' => env('SERVER_TIMING_MEASURE_HTTP', false),

    /*
     |--------------------------------------------------------------------------
     | Measure queued jobs
     |--------------------------------------------------------------------------
     |
     | This setting, if enabled, will count the jobs pushed to a queue during
     | the request. Jobs executed by the sync driver are not counted.
     */
    'measure_queue' => env('SERVER_TIMING_MEASURE_QUEUE', false),
];
