<?php

return [
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'USD'),
        'checkout_success_url' => env(
            'STRIPE_CHECKOUT_SUCCESS_URL',
            'http://localhost:5173/orders/{ORDER_ID}?stripe=success&session_id={CHECKOUT_SESSION_ID}'
        ),
        'checkout_cancel_url' => env(
            'STRIPE_CHECKOUT_CANCEL_URL',
            'http://localhost:5173/orders/{ORDER_ID}?stripe=cancelled'
        ),
    ],

    'paypal' => [
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
    ],
];
