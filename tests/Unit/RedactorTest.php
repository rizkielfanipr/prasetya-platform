<?php

declare(strict_types=1);

use App\Support\Logging\Redactor;

it('redacts sensitive keys recursively and leaves operational keys', function (): void {
    expect(Redactor::redact([
        'operation' => 'x',
        'invoice' => 'PW-1',
        'Authorization' => 'Bearer a',
        'signature' => 'HMACSHA256=abc',
        'nested' => ['customer_email' => 'a@b.c', 'secret_key' => 's', 'password' => 'p', 'request_id' => 'r'],
    ]))->toBe([
        'operation' => 'x',
        'invoice' => 'PW-1',
        'Authorization' => '[REDACTED]',
        'signature' => '[REDACTED]',
        'nested' => ['customer_email' => '[REDACTED]', 'secret_key' => '[REDACTED]', 'password' => '[REDACTED]', 'request_id' => 'r'],
    ]);
});
