<?php
return [
        'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],
        'allowed_methods' => ['*'],
        'allowed_origins' => ['http://localhost:3000', 'http://127.0.0.1:3000'], // Double-vérifiez que votre port React est bien ici
        'allowed_origins_patterns' => [],
        'allowed_headers' => ['*'],
        'exposed_headers' => [],
        'max_age' => 3600, // <--- CHANGEZ CETTE LIGNE À 3600 !
        'supports_credentials' => true,
    ];
