<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('http.retry_attempts', 3);
    config()->set('http.retry_delay', 100);
});

it('applies retry logic when http.retry_enabled is true', function () {
    config()->set('http.retry_enabled', true);

    $attempts = 0;

    Http::fake([
        'https://example.com/*' => function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Connection failed');
        },
    ]);

    $request = Http::withOptions([]);

    expect(fn () => $request->withTimeoutRetry()->get('https://example.com/test'))
        ->toThrow(ConnectionException::class);

    expect($attempts)->toBe(3);
});

it('does not apply retry logic when http.retry_enabled is false', function () {
    config()->set('http.retry_enabled', false);

    $attempts = 0;

    Http::fake([
        'https://example.com/*' => function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Connection failed');
        },
    ]);

    $request = Http::withOptions([]);

    expect(fn () => $request->withTimeoutRetry()->get('https://example.com/test'))
        ->toThrow(ConnectionException::class);

    expect($attempts)->toBe(1);
});

it('returns 200 if the second attempt succeeds', function () {
    config()->set('http.retry_enabled', true);

    $attempts = 0;

    Http::fake([
        'https://example.com/*' => function () use (&$attempts) {
            $attempts++;
            if ($attempts < 2) {
                throw new ConnectionException('Connection failed');
            }

            return Http::response(['message' => 'ok'], 200);
        },
    ]);

    $request = Http::withOptions([]);

    $response = $request->withTimeoutRetry()->get('https://example.com/test');

    expect($response->status())->toBe(200);
    expect($response->json('message'))->toBe('ok');
    expect($attempts)->toBe(2);
});
