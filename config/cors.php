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
        'https://analytics-dashboard.up.railway.app', 
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'http://localhost:3000',
        '*'
    ],
    
    'allowed_origins_patterns' => [
        '/^https:\/\/.*\.up\.railway\.app$/',
        '/^https:\/\/.*\.railway\.app$/',
    ],
    
    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'Origin',
        'X-CSRF-TOKEN',
        'X-API-Key', // Jika ada
    ],
    
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => true,
];