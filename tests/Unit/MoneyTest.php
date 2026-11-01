<?php

declare(strict_types=1);

use App\Support\Money;

it('multiplies without floats', function (): void {
    expect((new Money(499000))->times(3)->amount)->toBe(1497000);
});

it('rejects negative amounts and non-positive quantities', function (): void {
    expect(fn () => new Money(-1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new Money(1))->times(0))->toThrow(InvalidArgumentException::class);
});

it('compares amount and currency', function (): void {
    expect((new Money(100))->equals(new Money(100)))->toBeTrue()
        ->and((new Money(100))->equals(new Money(100, 'USD')))->toBeFalse();
});
