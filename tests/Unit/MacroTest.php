<?php

use Illuminate\Http\Client\PendingRequest;
use X3dgoo\HttpTimeoutRetryProvider\Providers\HttpTimeoutRetryProvider;

it('registers the macro correctly', function () {
    $this->app->register(HttpTimeoutRetryProvider::class);

    expect(PendingRequest::hasMacro('withTimeoutRetry'))->toBeTrue();
});
