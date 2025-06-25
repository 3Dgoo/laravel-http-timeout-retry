# HTTP Timeout Retry Provider for Laravel

A simple Laravel package that adds a macro to the HTTP client for automatic retrying of requests that fail due to connection timeouts.

## Features

- Adds a `withTimeoutRetry()` macro to Laravel's HTTP client.
- Retries requests on `ConnectionException` (timeout).
- Configurable retry attempts, delay, and enable/disable via config or environment variables.
- Optional logging of retry attempts with configurable log levels and channels.

## Installation

Require the package via Composer:

```bash
composer require 3dgoo/laravel-http-timeout-retry-provider
```

The service provider will be auto-discovered by Laravel.

## Configuration

You can publish the configuration file:

```bash
php artisan vendor:publish --provider="X3dgoo\HttpTimeoutRetryProvider\Providers\HttpTimeoutRetryProvider" --tag=config
```

Or, set the following environment variables in your .env file:

```env
HTTP_RETRY_ENABLED=true
HTTP_RETRY_ATTEMPTS=3
HTTP_RETRY_DELAY=100
HTTP_RETRY_LOG_ENABLED=false
HTTP_RETRY_LOG_LEVEL=info
HTTP_RETRY_LOG_CHANNEL=
```

### Configuration Options

- **HTTP_RETRY_ENABLED**: Master switch to enable/disable retry functionality
- **HTTP_RETRY_ATTEMPTS**: Number of retry attempts
- **HTTP_RETRY_DELAY**: Base delay between retries in milliseconds
- **HTTP_RETRY_LOG_ENABLED**: Enable logging of retry attempts
- **HTTP_RETRY_LOG_LEVEL**: Log level for retry messages
- **HTTP_RETRY_LOG_CHANNEL**: Custom log channel (optional)

## Usage

Use the withTimeoutRetry() macro on any HTTP client request:

```php
Http::withTimeoutRetry()->get('https://example.com/api');
```

We can also pass optional parameters to override the default number of retry attempts, the default retry delay, a callback function to override the retry logic, and enable/disable logging:

```php
Http::withTimeoutRetry(
    5,
    250,
    function ($exception) {
        return $exception instanceof \RuntimeException;
    },
    true
)->get('https://example.com/api');
```

This will retry the request up to the configured number of times if a connection timeout occurs.

## Logging

When logging is enabled, retry attempts will be logged with detailed information:

- **Log Level**: Configurable via `HTTP_RETRY_LOG_LEVEL` (default: `info`)
- **Log Channel**: Configurable via `HTTP_RETRY_LOG_CHANNEL` (uses default log channel if not set)
- **Log Message**: Includes attempt number, total attempts, and exception details

Example log entry:
```
[2025-06-25 12:00:00] local.info: HTTP request retry attempt 1/3 failed: Connection timeout
{
    "attempt": 1,
    "total_attempts": 3,
    "exception_class": "Illuminate\\Http\\Client\\ConnectionException",
    "exception_message": "Connection timeout"
}
```

## Configuration File

Default config (config/http.php):

```php
return [
    'retry' => [
        'enabled' => env('HTTP_RETRY_ENABLED', true),
        'attempts' => env('HTTP_RETRY_ATTEMPTS', 3),
        'delay' => env('HTTP_RETRY_DELAY', 100),
        'logging' => [
            'enabled' => env('HTTP_RETRY_LOG_ENABLED', false),
            'level' => env('HTTP_RETRY_LOG_LEVEL', 'info'),
            'channel' => env('HTTP_RETRY_LOG_CHANNEL', null),
        ],
    ],
];
```

## License

MIT