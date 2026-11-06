<?php

declare(strict_types=1);

// Contract source: docs.duitku.com (Duitku Pop). See docs/api/duitku-contract.md.
return [
    'environment' => env('DUITKU_ENVIRONMENT', 'sandbox'),
    'api_urls' => [
        'sandbox' => 'https://api-sandbox.duitku.com',
        'production' => 'https://api-prod.duitku.com',
    ],
    'status_urls' => [
        'sandbox' => 'https://sandbox.duitku.com',
        'production' => 'https://passport.duitku.com',
    ],
    'merchant_code' => env('DUITKU_MERCHANT_CODE'),
    'api_key' => env('DUITKU_API_KEY'),

    // Server-to-server address given to Duitku on each invoice. Empty means APP_URL plus the route's path.
    'callback_url' => env('DUITKU_CALLBACK_URL'),

    // Empty shows every active method. A code such as SP, NQ, or SQ limits checkout to that QRIS channel.
    'payment_method' => env('DUITKU_PAYMENT_METHOD'),

    'enforce_callback_ip' => (bool) env('DUITKU_ENFORCE_CALLBACK_IP', true),
    'callback_ips' => [
        'sandbox' => ['182.23.85.11', '182.23.85.12', '103.177.101.187', '103.177.101.188'],
        'production' => ['182.23.85.8', '182.23.85.9', '182.23.85.10', '182.23.85.13', '182.23.85.14', '103.177.101.184', '103.177.101.185', '103.177.101.186', '103.177.101.189', '103.177.101.190'],
    ],

    'timeout_seconds' => (int) env('DUITKU_TIMEOUT_SECONDS', 15),
    'connect_timeout_seconds' => (int) env('DUITKU_CONNECT_TIMEOUT_SECONDS', 5),
    'connect_retries' => (int) env('DUITKU_CONNECT_RETRIES', 1),
];
