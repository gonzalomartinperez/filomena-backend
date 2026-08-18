<?php

return [
    'initial_admin' => [
        'dni' => env('INITIAL_ADMIN_DNI'),
        'record_id' => env('INITIAL_ADMIN_RECORD_ID'),
        'username' => env('INITIAL_ADMIN_USERNAME'),
        'email' => env('INITIAL_ADMIN_EMAIL'),
        'first_name' => env('INITIAL_ADMIN_FIRST_NAME'),
        'last_name' => env('INITIAL_ADMIN_LAST_NAME'),
        'password' => env('INITIAL_ADMIN_PASSWORD'),
    ],

    'demo_user_password' => env('DEMO_USER_PASSWORD'),
];
