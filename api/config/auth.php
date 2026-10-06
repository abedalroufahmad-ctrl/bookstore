<?php

use App\Models\Customer;
use App\Models\Employee;

return [
    'defaults' => [
        'guard' => 'customer',
        'passwords' => 'customers',
    ],

    'guards' => [
        'employee' => [
            'driver' => 'jwt',
            'provider' => 'employees',
            'hash' => false,
        ],
        'customer' => [
            'driver' => 'jwt',
            'provider' => 'customers',
            'hash' => false,
        ],
    ],

    'providers' => [
        'employees' => [
            'driver' => 'eloquent',
            'model' => Employee::class,
        ],
        'customers' => [
            'driver' => 'eloquent',
            'model' => Customer::class,
        ],
    ],

    'passwords' => [
        'customers' => [
            'provider' => 'customers',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
