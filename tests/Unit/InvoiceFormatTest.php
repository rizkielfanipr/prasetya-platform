<?php

declare(strict_types=1);

use App\Domain\Brands\Enums\ApplicationCode;
use App\Domain\Orders\Services\InvoiceNumberGenerator;

it('formats invoices with brand prefix, period, and padding', function (): void {
    expect(InvoiceNumberGenerator::format(ApplicationCode::Webworks->invoicePrefix(), '202611', 128))->toBe('PW-202611-000128')
        ->and(InvoiceNumberGenerator::format(ApplicationCode::Satamu->invoicePrefix(), '202611', 1))->toBe('SAT-202611-000001');
});

it('fits the merchantOrderId limit of Duitku, retries included', function (): void {
    expect(strlen(InvoiceNumberGenerator::format('SAT', '202611', 999999).'-999'))->toBeLessThanOrEqual(50);
});
