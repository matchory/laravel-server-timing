<?php

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

    'measure_database' => env('SERVER_TIMING_MEASURE_DATABASE', true),
    'measure_queries' => env('SERVER_TIMING_MEASURE_QUERIES', false),
];
