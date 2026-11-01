<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use App\Support\Api\ApiErrorCode;
use App\Support\Api\ApiException;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

/**
 * Runs an operation at most once per (scope, key). Only a reference to the resulting resource is stored, not
 * the response body, so personal data is not duplicated and a replay shows the resource's current state.
 */
final class IdempotencyGate
{
    /**
     * @param  Closure(): array{id: string, status: int}  $operation
     * @return array{id: string, status: int, replayed: bool}
     */
    public function run(string $scope, ?string $key, string $fingerprint, Closure $operation): array
    {
        if ($key === null || $key === '' || strlen($key) > 128) {
            throw new ApiException(ApiErrorCode::IdempotencyKeyRequired, 'Header Idempotency-Key wajib diisi, maksimal 128 karakter.', 400);
        }

        $row = $this->acquire($scope, $key, $fingerprint);

        if ($row->status === IdempotencyKey::STATUS_COMPLETED) {
            return ['id' => (string) $row->resource_id, 'status' => (int) $row->response_status, 'replayed' => true];
        }

        try {
            $result = $operation();
        } catch (Throwable $e) {
            // A failed attempt created no resource to replay, so the client may retry with the same key.
            $row->delete();

            throw $e;
        }

        $row->update([
            'status' => IdempotencyKey::STATUS_COMPLETED,
            'resource_id' => $result['id'],
            'response_status' => $result['status'],
            'locked_until' => null,
        ]);

        return [...$result, 'replayed' => false];
    }

    private function acquire(string $scope, string $key, string $fingerprint): IdempotencyKey
    {
        $lockedUntil = now()->addSeconds((int) config('platform.idempotency_lock_seconds'));

        try {
            return IdempotencyKey::query()->create([
                'scope' => $scope,
                'idempotency_key' => $key,
                'request_fingerprint' => $fingerprint,
                'status' => IdempotencyKey::STATUS_IN_PROGRESS,
                'locked_until' => $lockedUntil,
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = IdempotencyKey::query()->where(['scope' => $scope, 'idempotency_key' => $key])->firstOrFail();
        }

        return match (IdempotencyDecision::for($existing, $fingerprint, now())) {
            IdempotencyDecision::Replay => $existing,
            IdempotencyDecision::Reused => throw new ApiException(ApiErrorCode::IdempotencyKeyReused, 'Idempotency-Key sudah dipakai untuk permintaan yang berbeda.', 422),
            IdempotencyDecision::InProgress => throw new ApiException(ApiErrorCode::IdempotencyInProgress, 'Permintaan yang sama masih diproses.', 409),
            IdempotencyDecision::TakeOver => tap($existing)->update(['locked_until' => $lockedUntil]),
        };
    }
}
