# Http timeout retry provider for Laravel

A Laravel package that adds retry functionality to Http requests with configurable timeout handling and HTTP method filtering.

## Features

- Adds a `withTimeoutRetry()` macro to Laravel's HTTP client
- Retries requests on `ConnectionException` (timeout) and other configurable exceptions
- **Http method filtering** - Configure which methods (GET, POST, etc.) are safe to retry
- Configurable retry attempts, delay, and enable/disable via config or environment variables
- Optional logging of retry attempts with configurable log levels and channels
- Runtime parameter overrides for fine-grained control

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
HTTP_RETRY_ALLOWED_METHODS=GET,HEAD,OPTIONS
HTTP_RETRY_LOGGING_ENABLED=false
HTTP_RETRY_LOGGING_LEVEL=info
HTTP_RETRY_LOGGING_CHANNEL=
```

### Configuration Options

- **HTTP_RETRY_ENABLED**: Master switch to enable/disable retry functionality
- **HTTP_RETRY_ATTEMPTS**: Number of retry attempts (0-100)
- **HTTP_RETRY_DELAY**: Base delay between retries in milliseconds (minimum 10ms)
- **HTTP_RETRY_ALLOWED_METHODS**: Comma-separated list of HTTP methods allowed for retry
- **HTTP_RETRY_LOGGING_ENABLED**: Enable logging of retry attempts
- **HTTP_RETRY_LOGGING_LEVEL**: Log level for retry messages
- **HTTP_RETRY_LOGGING_CHANNEL**: Custom log channel (optional)

## Usage

### Basic Usage

Use the `withTimeoutRetry()` macro on any HTTP client request:

```php
use Illuminate\Support\Facades\Http;

// Will retry GET requests (safe method)
$response = Http::withTimeoutRetry()->get('https://api.example.com/data');

// Will NOT retry POST requests by default (unsafe method)
$response = Http::withTimeoutRetry()->post('https://api.example.com/create', $data);
```

### Advanced Usage with Parameters

Override default settings for specific requests:

```php
Http::withTimeoutRetry(
    attempts: 5,
    delay: 250,
    callback: function ($exception) {
        return $exception instanceof \RuntimeException;
    },
    logRetries: true, 
    allowedMethods: ['POST', 'PUT']
)->post('https://api.example.com/create', $data);
```

### HTTP Method Filtering

By default, only safe HTTP methods are retried to prevent unintended side effects:

```php
// These methods are retried by default (safe, idempotent):
Http::withTimeoutRetry()->get('https://api.example.com/data');
Http::withTimeoutRetry()->head('https://api.example.com/check');
Http::withTimeoutRetry()->options('https://api.example.com/');

// These methods are NOT retried by default (potentially unsafe):
Http::withTimeoutRetry()->post('https://api.example.com/create');
Http::withTimeoutRetry()->put('https://api.example.com/update');
Http::withTimeoutRetry()->delete('https://api.example.com/item');
```

### Overriding Method Filtering

When you need to retry unsafe methods, override at runtime:

```php
// Allow specific methods for this request
Http::withTimeoutRetry(
    allowedMethods: ['POST', 'PUT']
)->post('https://api.example.com/idempotent-create', $data);

// Allow all methods for this request
Http::withTimeoutRetry(
    allowedMethods: ['*']
)->delete('https://api.example.com/resource/123');
```

## Method Safety Guide

Different HTTP methods have different safety characteristics:

### ✅ Safe Methods (Default retry enabled)
- **GET, HEAD, OPTIONS**: Idempotent and safe, no side effects
- These are the recommended defaults for retry

### ⚠️ Potentially Unsafe Methods (Require explicit opt-in)
- **POST**: Not idempotent, may create duplicate resources
- **PUT**: Idempotent by spec, but implementation varies
- **PATCH**: Not idempotent, partial updates can cause issues  
- **DELETE**: Idempotent but potentially destructive

### Configuration Examples

```php
// Safe methods only (recommended default)
'allowed_methods' => ['GET', 'HEAD', 'OPTIONS'],

// All read operations
'allowed_methods' => ['GET', 'HEAD', 'OPTIONS'],

// Include idempotent write operations (use with caution)
'allowed_methods' => ['GET', 'HEAD', 'OPTIONS', 'PUT'],

// All methods (not recommended as default)
'allowed_methods' => ['*'],
```

## Logging

When logging is enabled, retry attempts are logged with detailed information including the HTTP method:

- **Log Level**: Configurable via `HTTP_RETRY_LOGGING_LEVEL` (default: `info`)
- **Log Channel**: Configurable via `HTTP_RETRY_LOGGING_CHANNEL` (uses default log channel if not set)
- **Log Message**: Includes HTTP method, attempt number, total attempts, URL, and exception details

Example log entry:
```
[2025-06-25 12:00:00] local.info: HTTP GET request retry attempt 1/3 failed for URL https://api.example.com/create: Connection timeout
{
    "attempt": 1,
    "total_attempts": 3,
    "exception_class": "Illuminate\\Http\\Client\\ConnectionException",
    "exception_message": "Connection timeout",
    "request_url": "https://api.example.com/create",
    "request_method": "GET"
}
```

## Configuration File

Default config (config/http.php):

```php
return [
    'retry' => [
        'enabled' => env('HTTP_RETRY_ENABLED', true),
        'attempts' => max(0, min(100, env('HTTP_RETRY_ATTEMPTS', 3))),
        'delay' => max(10, env('HTTP_RETRY_DELAY', 100)),
        'allowed_methods' => array_map('strtoupper', explode(',', env('HTTP_RETRY_ALLOWED_METHODS', 'GET,HEAD,OPTIONS'))),
        'logging' => [
            'enabled' => env('HTTP_RETRY_LOGGING_ENABLED', false),
            'level' => env('HTTP_RETRY_LOGGING_LEVEL', 'info'),
            'channel' => env('HTTP_RETRY_LOGGING_CHANNEL', null),
        ],
    ],
];
```

## License

MIT