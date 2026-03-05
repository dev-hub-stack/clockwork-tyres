<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Allows the Angular wholesale frontend to communicate with this API.
    | Only affects the /api/* routes; Filament admin is unaffected.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // Angular development server
        'http://localhost:4200',
        // Wholesale staging frontend
        'https://stage.tunerstopwholesale.com',
        // Wholesale production frontend
        'https://tunerstopwholesale.com',
        'https://www.tunerstopwholesale.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
