<?php

declare(strict_types=1);

return [
    // duitku or fake. The fake gateway refuses to boot in production (see AppServiceProvider).
    'payment_gateway' => env('PAYMENT_GATEWAY', 'fake'),

    // Customer return pages. A return is never evidence of payment.
    'return_urls' => [
        'WEBWORKS' => env('PAYMENT_RETURN_URL_WEBWORKS', 'https://prasetyawebworks.web.id/order/status/'),
        'SATAMU' => env('PAYMENT_RETURN_URL_SATAMU', 'https://satamu.prasetyawebworks.web.id/order/status/'),
    ],

    'payment_due_minutes' => (int) env('PAYMENT_DUE_MINUTES', 60),

    // Dates are stored in UTC and presented in this zone.
    'business_timezone' => env('BUSINESS_TIMEZONE', 'Asia/Jakarta'),

    'order_expiry_hours' => (int) env('ORDER_EXPIRY_HOURS', 72),
    'payment_expiry_grace_minutes' => (int) env('PAYMENT_EXPIRY_GRACE_MINUTES', 15),
    'stranded_checkout_minutes' => (int) env('STRANDED_CHECKOUT_MINUTES', 2),
    'idempotency_lock_seconds' => (int) env('IDEMPOTENCY_LOCK_SECONDS', 30),

    'reconciliation' => [
        'pending_too_long_minutes' => (int) env('RECONCILIATION_PENDING_MINUTES', 120),
        'no_attempt_after_minutes' => (int) env('RECONCILIATION_NO_ATTEMPT_MINUTES', 30),
    ],

    // Allowed browser origins live in config/cors.php, read by both the CORS middleware and EnforceApiOrigin.

    'admin' => [
        'name' => env('ADMIN_NAME', 'Super Admin'),
        'username' => env('ADMIN_USERNAME'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
