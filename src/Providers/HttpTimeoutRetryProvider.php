<?php

namespace X3dgoo\HttpTimeoutRetryProvider\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class HttpTimeoutRetryProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerTimeoutRetryMacro();
    }

    /**
     * Register the withTimeoutRetry macro for PendingRequest.
     */
    private function registerTimeoutRetryMacro(): void
    {
        /**
         * Add timeout retry logic for Http requests.
         *
         * @param  int|null  $attempts  Number of retry attempts (overrides config if set)
         * @param  int|null  $delay  Delay between attempts in milliseconds (overrides config if set)
         * @param  callable|null  $callback  Callback to determine if retry should occur (overrides default if set)
         * @param  bool|null  $logRetries  Whether to log retry attempts (overrides config if set)
         * @param  array|null  $allowedMethods  HTTP methods allowed for retry (overrides config if set)
         * @return PendingRequest
         */
        PendingRequest::macro('withTimeoutRetry', function (
            ?int $attempts = null,
            ?int $delay = null,
            ?callable $callback = null,
            ?bool $logRetries = null,
            ?array $allowedMethods = null
        ): PendingRequest {
            /** @var PendingRequest $this */
            if (! config('http.retry.enabled')) {
                return $this;
            }

            $config = HttpTimeoutRetryProvider::resolveRetryConfig($attempts, $delay, $logRetries, $allowedMethods);
            $callback = HttpTimeoutRetryProvider::buildRetryCallback($this, $callback, $config);

            return $this->retry(
                $config['attempts'],
                $config['delay'],
                $callback,
                false
            );
        });
    }

    /**
     * Resolve retry configuration from parameters and config.
     */
    public static function resolveRetryConfig(?int $attempts, ?int $delay, ?bool $logRetries, ?array $allowedMethods): array
    {
        return [
            'attempts' => $attempts ?? config('http.retry.attempts'),
            'delay' => $delay ?? config('http.retry.delay'),
            'logRetries' => $logRetries ?? config('http.retry.logging.enabled'),
            'allowedMethods' => $allowedMethods ?? config('http.retry.allowed_methods', []),
        ];
    }

    /**
     * Build the retry callback with method filtering and logging.
     */
    public static function buildRetryCallback(PendingRequest $request, ?callable $callback, array $config): callable
    {
        $callback = self::applyMethodFiltering($request, $callback, $config['allowedMethods']);

        if ($config['logRetries']) {
            $callback = self::applyLogging($request, $callback, $config['attempts']);
        }

        return $callback;
    }

    /**
     * Apply HTTP method filtering to the retry callback.
     */
    private static function applyMethodFiltering(PendingRequest $request, ?callable $callback, array $allowedMethods): callable
    {
        if (empty($allowedMethods) || in_array('*', $allowedMethods)) {
            return $callback ?? self::getDefaultCallback();
        }

        $requestMethod = '';
        $request->withMiddleware(self::createMethodCaptureMiddleware($requestMethod));

        $originalCallback = $callback ?? self::getDefaultCallback();

        return function (\Exception $exception) use ($originalCallback, &$requestMethod, $allowedMethods) {
            if (! $originalCallback($exception)) {
                return false;
            }

            return in_array($requestMethod, array_map('strtoupper', $allowedMethods));
        };
    }

    /**
     * Apply logging to the retry callback.
     */
    private static function applyLogging(PendingRequest $request, callable $callback, int $totalAttempts): callable
    {
        $attemptCounter = 0;
        $requestUrl = '';
        $requestMethod = '';

        $request->withMiddleware(self::createRequestCaptureMiddleware($requestUrl, $requestMethod));

        return function (\Exception $exception) use ($callback, &$attemptCounter, $totalAttempts, &$requestUrl, &$requestMethod) {
            $shouldRetry = $callback($exception);

            if ($shouldRetry) {
                $attemptCounter++;
                self::logRetryAttempt($exception, $attemptCounter, $totalAttempts, $requestUrl, $requestMethod);
            }

            return $shouldRetry;
        };
    }

    /**
     * Get the default retry callback.
     */
    private static function getDefaultCallback(): callable
    {
        return function (\Exception $exception) {
            return $exception instanceof ConnectionException;
        };
    }

    /**
     * Create middleware to capture the HTTP method.
     */
    private static function createMethodCaptureMiddleware(string &$requestMethod): callable
    {
        return function ($handler) use (&$requestMethod) {
            return function ($request, $options) use ($handler, &$requestMethod) {
                $requestMethod = strtoupper($request->getMethod());

                return $handler($request, $options);
            };
        };
    }

    /**
     * Create middleware to capture request URL and method.
     */
    private static function createRequestCaptureMiddleware(string &$requestUrl, string &$requestMethod): callable
    {
        return function ($handler) use (&$requestUrl, &$requestMethod) {
            return function ($request, $options) use ($handler, &$requestUrl, &$requestMethod) {
                $requestUrl = (string) $request->getUri();
                $requestMethod = strtoupper($request->getMethod());

                return $handler($request, $options);
            };
        };
    }

    /**
     * Log a retry attempt.
     */
    public static function logRetryAttempt(\Exception $exception, int $currentAttempt, int $totalAttempts, string $requestUrl = '', string $requestMethod = ''): void
    {
        $logLevel = config('http.retry.logging.level', 'info');
        $logChannel = config('http.retry.logging.channel');

        $context = [
            'attempt' => $currentAttempt,
            'total_attempts' => $totalAttempts,
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'request_url' => $requestUrl,
            'request_method' => $requestMethod,
        ];

        $message = sprintf(
            'HTTP %s request retry attempt %d/%d failed for URL %s: %s',
            $requestMethod ?: 'UNKNOWN',
            $currentAttempt,
            $totalAttempts,
            $requestUrl ?: 'unknown',
            $exception->getMessage()
        );

        if ($logChannel) {
            try {
                Log::channel($logChannel)->log($logLevel, $message, $context);
            } catch (\Exception $e) {
                Log::log($logLevel, $message, $context);
            }
        } else {
            Log::log($logLevel, $message, $context);
        }
    }
}
