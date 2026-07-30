<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for cross-origin requests. The "paths" and "allowed_origins"
    | arrays support wildcard (*) matching.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],
 
    'allowed_origins' => [
        'https://kantest.lspgatensi.id',
        'https://kan.lspgatensi.id',
        'http://localhost:5173',
        'http://localhost:3000',
    ],

    'allowed_origins_patterns' => ['/https:\/\/kan(-test|test)?\.lspgatensi\.id$/'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
