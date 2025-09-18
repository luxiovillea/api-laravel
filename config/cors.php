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
        'https://analytics-dashboard-production-c30c9.up.railway.app', // Tambahkan ini
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'http://localhost:3000',
        '*' // Untuk development/testing - hapus di production
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