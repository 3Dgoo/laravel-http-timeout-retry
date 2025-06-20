<?php

namespace X3dgoo\HttpTimeoutRetryProvider\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
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
         * @return PendingRequest
         */
        PendingRequest::macro('withTimeoutRetry', function (
            ?int $attempts = null,
            ?int $delay = null,
            ?callable $callback = null
        ): PendingRequest {
            /** @var PendingRequest $this */
            if (! config('http.retry_enabled')) {
                return $this;
            }

            $attempts = $attempts ?? config('http.retry_attempts');
            $delay = $delay ?? config('http.retry_delay');
            $callback = $callback ?? function (\Exception $exception) {
                return $exception instanceof ConnectionException;
            };

            return $this->retry(
                $attempts,
                $delay,
                $callback
            );
        });
    }
}
