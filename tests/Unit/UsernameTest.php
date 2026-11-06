<?php

declare(strict_types=1);

use App\Domain\Auth\Username;

it('normalises to trimmed lower case', function (): void {
    expect(Username::normalize('  Sari.Lestari '))->toBe('sari.lestari');
});

it('accepts valid usernames', function (string $username): void {
    expect(Username::isValid($username))->toBeTrue();
})->with(['abc', 'sari', 'sari.lestari', 'budi_s', 'demo-2', 'a1b', str_repeat('a', 32)]);

it('rejects invalid usernames', function (string $username): void {
    expect(Username::isValid($username))->toBeFalse();
})->with(['ab', str_repeat('a', 33), 'Sari', '.sari', 'sari.', '-sari', 'sari_', 'sa ri', 'sari@x', 'ŝari', '']);
