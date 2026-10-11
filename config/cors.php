<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:*',
        'http://127.0.0.1:*',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'https://emis.kemenag.go.id',
    ],

    'allowed_origins_patterns' => [
        '#^chrome-extension://.*#',
        '#^moz-extension://.*#',
    ],

    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'Accept',
        'Origin',
        'Idempotency-Key',
        'X-CSRF-TOKEN',
    ],

    'exposed_headers' => [
        'Idempotency-Key',
    ],

    'max_age' => 86400,

    'supports_credentials' => true,

];
