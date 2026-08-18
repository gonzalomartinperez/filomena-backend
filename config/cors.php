<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Si FRONTEND_URL está definido => lo usamos como único origin.
    // Si no, fallback permisivo (útil en primeras pruebas locales).
    'allowed_origins' => (static function () {
        $f = env('FRONTEND_URL');

        return $f ? [rtrim($f, '/')] : ['*'];
    })(),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
