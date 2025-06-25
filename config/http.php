<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HTTP Retry Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration controls the retry behavior for HTTP requests using
    | the withTimeoutRetry() macro. These settings can be overridden at
    | runtime when calling the macro or via environment variables.
    |
    */

    'retry' => [
        /*
        |--------------------------------------------------------------------------
        | Retry Enabled
        |--------------------------------------------------------------------------
        |
        | Master switch to enable or disable the retry functionality globally.
        | When disabled, the withTimeoutRetry() macro will not perform any retries.
        |
        */
        'enabled' => filter_var(env('HTTP_RETRY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

        /*
        |--------------------------------------------------------------------------
        | Retry Attempts
        |--------------------------------------------------------------------------
        |
        | The number of times to retry a failed HTTP request. Set to 0 to disable
        | retries entirely. Maximum value is 100.
        |
        */
        'attempts' => max(0, min(100, (int) env('HTTP_RETRY_ATTEMPTS', 3))),

        /*
        |--------------------------------------------------------------------------
        | Retry Delay
        |--------------------------------------------------------------------------
        |
        | The delay in milliseconds between retry attempts. Minimum is 10ms.
        |
        */
        'delay' => max(10, (int) env('HTTP_RETRY_DELAY', 100)),

        /*
        |--------------------------------------------------------------------------
        | Retry Logging Configuration
        |--------------------------------------------------------------------------
        |
        | Controls how retry attempts are logged for debugging and monitoring.
        |
        */
        'logging' => [
            /*
            |--------------------------------------------------------------------------
            | Log Enabled
            |--------------------------------------------------------------------------
            |
            | Enable or disable logging of retry attempts.
            |
            */
            'enabled' => filter_var(env('HTTP_RETRY_LOGGING_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

            /*
            |--------------------------------------------------------------------------
            | Log Level
            |--------------------------------------------------------------------------
            |
            | The log level to use when logging retry attempts. Valid levels are:
            | emergency, alert, critical, error, warning, notice, info, debug
            |
            */
            'level' => in_array(env('HTTP_RETRY_LOGGING_LEVEL', 'info'), [
                'emergency',
                'alert',
                'critical',
                'error',
                'warning',
                'notice',
                'info',
                'debug',
            ]) ? env('HTTP_RETRY_LOGGING_LEVEL', 'info') : 'info',

            /*
            |--------------------------------------------------------------------------
            | Log Channel
            |--------------------------------------------------------------------------
            |
            | The log channel to use for retry logging. If null, uses the default
            | application log channel defined in config/logging.php
            |
            */
            'channel' => env('HTTP_RETRY_LOGGING_CHANNEL', null),
        ],
    ],
];
