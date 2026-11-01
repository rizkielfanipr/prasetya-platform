<?php

declare(strict_types=1);

// Contract source: developers.doku.com (DOKU Checkout, non-SNAP). See docs/api/doku-contract.md.
return [
    'environment' => env('DOKU_ENVIRONMENT', 'sandbox'),
    'base_urls' => [
        'sandbox' => 'https://api-sandbox.doku.com',
        'production' => 'https://api.doku.com',
    ],
    'client_id' => env('DOKU_CLIENT_ID'),
    'secret_key' => env('DOKU_SECRET_KEY'),

    // Request-Target of inbound notifications: the path of the Notification URL set in DOKU Back Office.
    'notification_path' => env('DOKU_NOTIFICATION_PATH', '/api/v1/webhooks/doku'),

    // 0 disables the age check. DOKU retries for up to 12 hours and allows manual resend, and does not
    // document whether retries are re-signed, so duplicate detection is the primary replay control.
    'notification_max_age_seconds' => (int) env('DOKU_NOTIFICATION_MAX_AGE_SECONDS', 0),

    'payment_due_minutes' => (int) env('DOKU_PAYMENT_DUE_MINUTES', 60),
    'timeout_seconds' => (int) env('DOKU_TIMEOUT_SECONDS', 15),
    'connect_timeout_seconds' => (int) env('DOKU_CONNECT_TIMEOUT_SECONDS', 5),
    'connect_retries' => (int) env('DOKU_CONNECT_RETRIES', 1),

    // Customer return pages. A return is never evidence of payment.
    'callback_urls' => [
        'WEBWORKS' => env('DOKU_CALLBACK_URL_WEBWORKS', 'https://prasetyawebworks.web.id/order/status/'),
        'SATAMU' => env('DOKU_CALLBACK_URL_SATAMU', 'https://satamu.prasetyawebworks.web.id/order/status/'),
    ],
];
