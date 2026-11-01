<?php

declare(strict_types=1);

namespace App\Domain\Audit\Models;

use App\Domain\Auth\Models\User;
use App\Support\Eloquent\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $actor_label
 * @property string $action
 * @property string $entity_type
 * @property string|null $entity_id
 * @property array<string, mixed>|null $previous_state
 * @property array<string, mixed>|null $new_state
 * @property string|null $reason
 * @property string|null $request_id
 * @property Carbon $created_at
 */
class AuditLog extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    protected $guarded = [];

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return ['previous_state' => 'array', 'new_state' => 'array'];
    }
}
