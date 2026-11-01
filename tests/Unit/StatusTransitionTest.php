<?php

declare(strict_types=1);

use App\Domain\Orders\Enums\OrderStatus as O;
use App\Domain\Payments\Enums\PaymentStatus as P;

it('allows exactly the documented payment transitions', function (): void {
    $allowed = [];

    foreach (P::cases() as $from) {
        foreach (P::cases() as $to) {
            if ($from->canTransitionTo($to)) {
                $allowed[] = "{$from->value}>{$to->value}";
            }
        }
    }

    expect($allowed)->toEqualCanonicalizing([
        'CREATED>PENDING', 'CREATED>PAID', 'CREATED>FAILED', 'CREATED>EXPIRED', 'CREATED>CANCELLED',
        'PENDING>PAID', 'PENDING>FAILED', 'PENDING>EXPIRED', 'PENDING>CANCELLED',
        'FAILED>PAID', 'EXPIRED>PAID',
    ]);
});

it('allows exactly the documented order transitions', function (): void {
    $allowed = [];

    foreach (O::cases() as $from) {
        foreach (O::cases() as $to) {
            if ($from->canTransitionTo($to)) {
                $allowed[] = "{$from->value}>{$to->value}";
            }
        }
    }

    expect($allowed)->toEqualCanonicalizing([
        'DRAFT>PENDING_PAYMENT', 'DRAFT>CANCELLED',
        'PENDING_PAYMENT>CONFIRMED', 'PENDING_PAYMENT>CANCELLED', 'PENDING_PAYMENT>EXPIRED',
        'EXPIRED>CONFIRMED',
    ]);
});

it('treats PAID as final', function (): void {
    expect(P::Paid->allowedTransitions())->toBe([]);
});
