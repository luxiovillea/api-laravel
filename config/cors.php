<?php

return [
    'paths' => [
        'api/*', 
        'sanctum/csrf-cookie',
        'api/documentation',
        'docs/*'
    ],
    
    'allowed_methods' => ['*'],
    
    'allowed_origins' => [
        'https://api-laravel-production-39d6.up.railway.app',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'http://localhost:3000',
    ],
    
    'allowed_origins_patterns' => [
        '/^https:\/\/.*\.up\.railway\.app$/',
    ],
    
    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'Origin',
        'X-CSRF-TOKEN',
    ],
    
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => true,
];