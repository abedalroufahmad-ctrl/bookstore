<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PayPal REST environment
    |--------------------------------------------------------------------------
    |
    | Use "sandbox" for developer testing and "live" for production.
    |
    */
    'mode' => env('PAYPAL_MODE', 'sandbox'),

    'client_id' => env('PAYPAL_CLIENT_ID', ''),

    'secret' => env('PAYPAL_CLIENT_SECRET', ''),

    'currency' => env('PAYPAL_CURRENCY', 'USD'),

    /*
    | Outbound HTTP limits (seconds). Without them a slow PayPal response holds a PHP worker
    | for the default socket timeout and can exhaust the pool under load.
    */
    'http_timeout' => (int) env('PAYPAL_HTTP_TIMEOUT', 20),

    'http_connect_timeout' => (int) env('PAYPAL_HTTP_CONNECT_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Redirect URLs (browser flow)
    |--------------------------------------------------------------------------
    |
    | return_url is sent to PayPal when creating the order; PayPal appends ?token=...
    | to this URL after the buyer approves. It should point to this API's
    | GET /v1/paypal/complete route (see PAYPAL_RETURN_URL in .env.example).
    |
    | success_redirect is where the API sends the buyer after a successful capture.
    | cancel_redirect is used as PayPal cancel_url (e.g. back to checkout).
    |
    */
    'return_url' => env('PAYPAL_RETURN_URL', ''),

    'success_redirect' => env('PAYPAL_SUCCESS_REDIRECT', ''),

    'cancel_redirect' => env('PAYPAL_CANCEL_REDIRECT', ''),
];
