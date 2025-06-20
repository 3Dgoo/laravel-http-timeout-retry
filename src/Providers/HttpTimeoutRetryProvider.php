<?php

namespace X3dgoo\HttpTimeoutRetryProvider\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\ServiceProvider;

class HttpTimeoutRetryProvider extends ServiceProvider
{
    public function boot(): void
    {
        PendingRequest::macro('withTimeoutRetry', function (): PendingRequest {
            /** @var PendingRequest $this */
            if (! config('http.retry_enabled')) {
                return $this;
            }

            return $this->retry(
                config('http.retry_attempts'),
                config('http.retry_delay'),
                function (\Exception $exception) {
                    return $exception instanceof ConnectionException;
                }
            );
        });
    }
}
