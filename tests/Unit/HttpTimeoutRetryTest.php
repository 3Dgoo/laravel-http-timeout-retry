<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use X3dgoo\HttpTimeoutRetryProvider\Providers\HttpTimeoutRetryProvider;

beforeEach(function () {
    $this->app['config']->set('http.retry.attempts', 3);
    $this->app['config']->set('http.retry.delay', 100);

    $this->app->register(HttpTimeoutRetryProvider::class);
});

it('applies retry logic when http.retry.enabled is true', function () {
    $this->app['config']->set('http.retry.enabled', true);

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

it('does not apply retry logic when http.retry.enabled is false', function () {
    $this->app['config']->set('http.retry.enabled', false);

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
    $this->app['config']->set('http.retry.enabled', true);

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

it('overrides attempts and delay when parameters are passed', function () {
    $this->app['config']->set('http.retry.enabled', true);
    $this->app['config']->set('http.retry.attempts', 5);
    $this->app['config']->set('http.retry.delay', 200);

    $attempts = 0;

    Http::fake([
        'https://example.com/*' => function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Connection failed');
        },
    ]);

    $request = Http::withOptions([]);

    expect(fn () => $request->withTimeoutRetry(2, 50)->get('https://example.com/test'))
        ->toThrow(ConnectionException::class);

    expect($attempts)->toBe(2);
});

it('uses custom callback if provided', function () {
    $this->app['config']->set('http.retry.enabled', true);

    $attempts = 0;

    Http::fake([
        'https://example.com/*' => function () use (&$attempts) {
            $attempts++;
            throw new \RuntimeException('Some error');
        },
    ]);

    $request = Http::withOptions([]);

    $callback = function ($exception) {
        return $exception instanceof \RuntimeException;
    };

    expect(fn () => $request->withTimeoutRetry(3, 10, $callback)->get('https://example.com/test'))
        ->toThrow(\RuntimeException::class);

    expect($attempts)->toBe(3);
});

it('logs retry attempts when logging is enabled', function () {
    $this->app['config']->set('http.retry.enabled', true);
    $this->app['config']->set('http.retry.logging.enabled', true);
    $this->app['config']->set('http.retry.logging.level', 'info');

    Log::spy();

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

    Log::shouldHaveReceived('log')
        ->with('info', \Mockery::pattern('/HTTP GET request retry attempt .* failed for URL .* Connection failed/'), \Mockery::type('array'))
        ->twice();
});

it('logs request URL in retry attempts', function () {
    $this->app['config']->set('http.retry.enabled', true);
    $this->app['config']->set('http.retry.logging.enabled', true);
    $this->app['config']->set('http.retry.logging.level', 'info');

    Log::spy();

    $attempts = 0;

    Http::fake([
        'https://api.example.com/*' => function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Connection failed');
        },
    ]);

    $request = Http::baseUrl('https://api.example.com');

    expect(fn () => $request->withTimeoutRetry()->get('/users'))
        ->toThrow(ConnectionException::class);

    expect($attempts)->toBe(3);

    Log::shouldHaveReceived('log')
        ->with('info', \Mockery::pattern('/HTTP GET request retry attempt .* failed for URL https:\/\/api\.example\.com\/users.*Connection failed/'), \Mockery::on(function ($context) {
            return isset($context['request_url']) &&
                   $context['request_url'] === 'https://api.example.com/users' &&
                   isset($context['attempt']) &&
                   isset($context['total_attempts']) &&
                   isset($context['exception_class']) &&
                   isset($context['exception_message']);
        }))
        ->twice();
});

it('logs full URL when using direct URL calls', function () {
    $this->app['config']->set('http.retry.enabled', true);
    $this->app['config']->set('http.retry.logging.enabled', true);
    $this->app['config']->set('http.retry.logging.level', 'info');

    Log::spy();

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

    Log::shouldHaveReceived('log')
        ->with('info', \Mockery::pattern('/HTTP GET request retry attempt .* failed for URL https:\/\/example\.com\/test.*Connection failed/'), \Mockery::on(function ($context) {
            return isset($context['request_url']) &&
                   $context['request_url'] === 'https://example.com/test' &&
                   isset($context['attempt']) &&
                   isset($context['total_attempts']);
        }))
        ->twice();
});

it('does not log retry attempts when logging is disabled', function () {
    $this->app['config']->set('http.retry.enabled', true);
    $this->app['config']->set('http.retry.logging.enabled', false);

    Log::spy();

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

    Log::shouldNotHaveReceived('log');
});

it('uses custom log channel when specified', function () {
    $this->app['config']->set('http.retry.enabled', true);
    $this->app['config']->set('http.retry.logging.enabled', true);
    $this->app['config']->set('http.retry.logging.channel', null);

    Log::spy();

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
    expect($attempts)->toBe(2);

    Log::shouldHaveReceived('log')
        ->with('info', \Mockery::pattern('/HTTP GET request retry attempt .* failed for URL/'), \Mockery::type('array'))
        ->once();
});

it('can override logging setting via parameter', function () {
    $this->app['config']->set('http.retry.enabled', true);
    $this->app['config']->set('http.retry.logging.enabled', false);

    Log::spy();

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

    $response = $request->withTimeoutRetry(null, null, null, true)->get('https://example.com/test');

    expect($response->status())->toBe(200);
    expect($attempts)->toBe(2);

    Log::shouldHaveReceived('log')
        ->with('info', \Mockery::pattern('/HTTP GET request retry attempt .* failed for URL/'), \Mockery::type('array'))
        ->once();
});

it('uses custom log level when specified', function () {
    $this->app['config']->set('http.retry.enabled', true);
    $this->app['config']->set('http.retry.logging.enabled', true);
    $this->app['config']->set('http.retry.logging.level', 'error');

    Log::spy();

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
    expect($attempts)->toBe(2);

    Log::shouldHaveReceived('log')
        ->with('error', \Mockery::pattern('/HTTP GET request retry attempt .* failed for URL/'), \Mockery::type('array'))
        ->once();
});

it('logRetryAttempt method includes URL in context', function () {
    $this->app['config']->set('http.retry.logging.level', 'warning');
    $this->app['config']->set('http.retry.logging.channel', null);

    Log::spy();

    $exception = new ConnectionException('Network timeout');
    $testUrl = 'https://api.test.com';

    HttpTimeoutRetryProvider::logRetryAttempt($exception, 2, 3, $testUrl);

    Log::shouldHaveReceived('log')
        ->with('warning', 'HTTP UNKNOWN request retry attempt 2/3 failed for URL https://api.test.com: Network timeout', \Mockery::on(function ($context) use ($testUrl) {
            return $context['attempt'] === 2 &&
                   $context['total_attempts'] === 3 &&
                   $context['exception_class'] === ConnectionException::class &&
                   $context['exception_message'] === 'Network timeout' &&
                   $context['request_url'] === $testUrl;
        }))
        ->once();
});

it('logRetryAttempt method handles empty URL gracefully', function () {
    $this->app['config']->set('http.retry.logging.level', 'info');
    $this->app['config']->set('http.retry.logging.channel', null);

    Log::spy();

    $exception = new ConnectionException('Connection failed');

    HttpTimeoutRetryProvider::logRetryAttempt($exception, 1, 2, '');

    Log::shouldHaveReceived('log')
        ->with('info', 'HTTP UNKNOWN request retry attempt 1/2 failed for URL unknown: Connection failed', \Mockery::on(function ($context) {
            return $context['attempt'] === 1 &&
                   $context['total_attempts'] === 2 &&
                   $context['exception_class'] === ConnectionException::class &&
                   $context['exception_message'] === 'Connection failed' &&
                   $context['request_url'] === '';
        }))
        ->once();
});

it('logs full URL for various request patterns', function () {
    $this->app['config']->set('http.retry.enabled', true);
    $this->app['config']->set('http.retry.logging.enabled', true);
    $this->app['config']->set('http.retry.logging.level', 'info');

    Log::spy();

    $attempts = 0;

    Http::fake([
        'https://test.api.com/*' => function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Connection failed');
        },
    ]);

    $request = Http::withOptions([]);

    expect(fn () => $request->withTimeoutRetry()->get('https://test.api.com/api/v1/users'))
        ->toThrow(ConnectionException::class);

    expect($attempts)->toBe(3);

    Log::shouldHaveReceived('log')
        ->with('info', \Mockery::pattern('/HTTP GET request retry attempt .* failed for URL https:\/\/test\.api\.com\/api\/v1\/users.*Connection failed/'), \Mockery::on(function ($context) {
            return isset($context['request_url']) &&
                   $context['request_url'] === 'https://test.api.com/api/v1/users' &&
                   isset($context['attempt']) &&
                   isset($context['total_attempts']) &&
                   isset($context['exception_class']) &&
                   isset($context['exception_message']);
        }))
        ->twice();
});

it('logs URL with query parameters', function () {
    $this->app['config']->set('http.retry.enabled', true);
    $this->app['config']->set('http.retry.logging.enabled', true);
    $this->app['config']->set('http.retry.logging.level', 'info');

    Log::spy();

    $attempts = 0;

    Http::fake([
        'https://search.api.com/*' => function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Connection failed');
        },
    ]);

    $request = Http::withOptions([]);

    expect(fn () => $request->withTimeoutRetry()->get('https://search.api.com/search?q=test&limit=10'))
        ->toThrow(ConnectionException::class);

    expect($attempts)->toBe(3);

    Log::shouldHaveReceived('log')
        ->with('info', \Mockery::pattern('/HTTP GET request retry attempt .* failed for URL https:\/\/search\.api\.com\/search\?q=test&limit=10.*Connection failed/'), \Mockery::on(function ($context) {
            return isset($context['request_url']) &&
                   $context['request_url'] === 'https://search.api.com/search?q=test&limit=10' &&
                   isset($context['attempt']) &&
                   isset($context['total_attempts']);
        }))
        ->twice();
});

// Test HTTP method filtering functionality
it('retries GET requests when GET is in allowed methods', function () {
    $this->app['config']->set('http.retry.enabled', true);
    $this->app['config']->set('http.retry.allowed_methods', ['GET', 'HEAD']);

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

it('does not retry POST requests when POST is not in allowed methods', function () {
    $this->app['config']->set('http.retry.enabled', true);
    $this->app['config']->set('http.retry.allowed_methods', ['GET', 'HEAD']);

    $attempts = 0;

    Http::fake([
        'https://example.com/*' => function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Connection failed');
        },
    ]);

    $request = Http::withOptions([]);

    expect(fn () => $request->withTimeoutRetry()->post('https://example.com/test'))
        ->toThrow(ConnectionException::class);

    expect($attempts)->toBe(1);
});

it('retries all methods when wildcard (*) is in allowed methods', function () {
    $this->app['config']->set('http.retry.enabled', true);
    $this->app['config']->set('http.retry.allowed_methods', ['*']);

    $attempts = 0;

    Http::fake([
        'https://example.com/*' => function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Connection failed');
        },
    ]);

    $request = Http::withOptions([]);

    expect(fn () => $request->withTimeoutRetry()->post('https://example.com/test'))
        ->toThrow(ConnectionException::class);

    expect($attempts)->toBe(3);
});

it('allows runtime override of allowed methods', function () {
    $this->app['config']->set('http.retry.enabled', true);
    $this->app['config']->set('http.retry.allowed_methods', ['GET']);

    $attempts = 0;

    Http::fake([
        'https://example.com/*' => function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Connection failed');
        },
    ]);

    $request = Http::withOptions([]);

    expect(fn () => $request->withTimeoutRetry(allowedMethods: ['POST'])->post('https://example.com/test'))
        ->toThrow(ConnectionException::class);

    expect($attempts)->toBe(3);
});

it('includes request method in log messages when logging is enabled', function () {
    $this->app['config']->set('http.retry.enabled', true);
    $this->app['config']->set('http.retry.logging.enabled', true);
    $this->app['config']->set('http.retry.allowed_methods', ['POST']);

    Log::spy();

    $attempts = 0;

    Http::fake([
        'https://example.com/*' => function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new ConnectionException('Connection failed');
            }

            return Http::response(['success' => true], 200);
        },
    ]);

    $request = Http::withOptions([]);
    $request->withTimeoutRetry()->post('https://example.com/test');

    expect($attempts)->toBe(3);

    Log::shouldHaveReceived('log')
        ->with('info', \Mockery::pattern('/HTTP POST request retry attempt .* failed for URL https:\/\/example\.com\/test.*Connection failed/'), \Mockery::on(function ($context) {
            return isset($context['request_method']) &&
                   $context['request_method'] === 'POST' &&
                   isset($context['request_url']) &&
                   $context['request_url'] === 'https://example.com/test';
        }))
        ->twice();
});

it('handles case-insensitive method comparison', function () {
    $this->app['config']->set('http.retry.enabled', true);
    $this->app['config']->set('http.retry.allowed_methods', ['get', 'post']);

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
