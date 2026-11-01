<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $scope
 * @property string $idempotency_key
 * @property string $request_fingerprint
 * @property string $status
 * @property string|null $resource_id
 * @property int|null $response_status
 * @property Carbon|null $locked_until
 */
final class IdempotencyKey extends Model
{
    public const string STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const string STATUS_COMPLETED = 'COMPLETED';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['locked_until' => 'datetime', 'response_status' => 'integer'];
    }
}
