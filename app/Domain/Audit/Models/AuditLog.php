<?php

declare(strict_types=1);

namespace App\Domain\Audit\Models;

use App\Domain\Auth\Models\User;
use App\Support\Eloquent\AppendOnly;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Entries about the given entities, so each screen shows only its own audit (ADR-0010).
     *
     * @param  Builder<self>  $query
     * @param  array<string, iterable<string>>  $entities  entity type => public ids
     */
    #[Scope]
    protected function forEntities(Builder $query, array $entities): void
    {
        $query->where(static function (Builder $any) use ($entities): void {
            foreach ($entities as $type => $ids) {
                $any->orWhere(static fn (Builder $one) => $one->where('entity_type', $type)->whereIn('entity_id', collect($ids)->all()));
            }
        });
    }

    protected function casts(): array
    {
        return ['previous_state' => 'array', 'new_state' => 'array'];
    }
}
