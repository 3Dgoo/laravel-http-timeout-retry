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
        /**
         * Add timeout retry logic for Http requests.
         *
         * @param  int|null  $attempts  Number of retry attempts (overrides config if set)
         * @param  int|null  $delay  Delay between attempts in milliseconds (overrides config if set)
         * @param  callable|null  $callback  Callback to determine if retry should occur (overrides default if set)
         * @param  bool|null  $logRetries  Whether to log retry attempts (overrides config if set)
         * @return PendingRequest
         */
        PendingRequest::macro('withTimeoutRetry', function (
            ?int $attempts = null,
            ?int $delay = null,
            ?callable $callback = null,
            ?bool $logRetries = null
        ): PendingRequest {
            /** @var PendingRequest $this */
            if (! config('http.retry.enabled')) {
                return $this;
            }

            $attempts = $attempts ?? config('http.retry.attempts');
            $delay = $delay ?? config('http.retry.delay');
            $logRetries = $logRetries ?? config('http.retry.logging.enabled');

            $callback = $callback ?? function (\Exception $exception) {
                return $exception instanceof ConnectionException;
            };

            if ($logRetries) {
                $originalCallback = $callback;
                $attemptCounter = 0;
                $requestUrl = '';

                $this->withMiddleware(function ($handler) use (&$requestUrl) {
                    return function ($request, $options) use ($handler, &$requestUrl) {
                        $requestUrl = (string) $request->getUri();

                        return $handler($request, $options);
                    };
                });

                $callback = function (\Exception $exception) use ($originalCallback, &$attemptCounter, $attempts, &$requestUrl) {
                    $shouldRetry = $originalCallback($exception);

                    if ($shouldRetry) {
                        $attemptCounter++;
                        HttpTimeoutRetryProvider::logRetryAttempt($exception, $attemptCounter, $attempts, $requestUrl);
                    }

                    return $shouldRetry;
                };
            }

            return $this->retry(
                $attempts,
                $delay,
                $callback,
                false
            );
        });
    }

    /**
     * Log a retry attempt.
     */
    public static function logRetryAttempt(\Exception $exception, int $currentAttempt, int $totalAttempts, string $requestUrl = ''): void
    {
        $logLevel = config('http.retry.logging.level', 'info');
        $logChannel = config('http.retry.logging.channel');

        $context = [
            'attempt' => $currentAttempt,
            'total_attempts' => $totalAttempts,
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'request_url' => $requestUrl,
        ];

        $message = sprintf(
            'HTTP request retry attempt %d/%d failed for URL %s: %s',
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
