<?php

return [
    'retry_attempts' => env('HTTP_RETRY_ATTEMPTS', 3),
    'retry_delay' => env('HTTP_RETRY_DELAY', 100),
    'retry_enabled' => env('HTTP_RETRY_ENABLED', true),
];
