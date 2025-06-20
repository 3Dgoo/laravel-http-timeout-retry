# HTTP Timeout Retry Provider for Laravel

A simple Laravel package that adds a macro to the HTTP client for automatic retrying of requests that fail due to connection timeouts.

## Features

- Adds a `withTimeoutRetry()` macro to Laravel's HTTP client.
- Retries requests on `ConnectionException` (timeout).
- Configurable retry attempts, delay, and enable/disable via config or environment variables.

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
HTTP_RETRY_ATTEMPTS=3
HTTP_RETRY_DELAY=100
HTTP_RETRY_ENABLED=true
```

## Usage

Use the withTimeoutRetry() macro on any HTTP client request:

```php
use Illuminate\Support\Facades\Http;

$response = Http::withTimeoutRetry()->get('https://example.com/api');
```

This will retry the request up to the configured number of times if a connection timeout occurs.

```Configuration File
Default config (config/http.php):

```php
return [
    'retry_attempts' => env('HTTP_RETRY_ATTEMPTS', 3),
    'retry_delay' => env('HTTP_RETRY_DELAY', 100),
    'retry_enabled' => env('HTTP_RETRY_ENABLED', true),
];
```

## License

MIT