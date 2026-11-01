<?php

declare(strict_types=1);

use App\Support\Idempotency\IdempotencyDecision as D;
use App\Support\Idempotency\IdempotencyKey;
use Illuminate\Support\Carbon;

function idemRow(string $status, ?Carbon $lockedUntil = null): IdempotencyKey
{
    return new IdempotencyKey(['request_fingerprint' => 'abc', 'status' => $status, 'locked_until' => $lockedUntil]);
}

it('decides from status, fingerprint, and lock', function (string $status, ?int $lockOffset, string $fingerprint, D $expected): void {
    $now = Carbon::parse('2026-11-01 00:00:00');
    $lock = $lockOffset === null ? null : $now->copy()->addSeconds($lockOffset);

    expect(D::for(idemRow($status, $lock), $fingerprint, $now))->toBe($expected);
})->with([
    'completed, same request' => ['COMPLETED', null, 'abc', D::Replay],
    'completed, different request' => ['COMPLETED', null, 'xyz', D::Reused],
    'running, different request' => ['IN_PROGRESS', 30, 'xyz', D::Reused],
    'running, lock held' => ['IN_PROGRESS', 30, 'abc', D::InProgress],
    'running, lock expired' => ['IN_PROGRESS', -1, 'abc', D::TakeOver],
]);
