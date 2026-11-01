<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\TransitionSource;
use App\Support\Eloquent\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property OrderStatus|null $from_status
 * @property OrderStatus $to_status
 * @property TransitionSource $source
 * @property string|null $reason
 * @property string|null $request_id
 * @property Carbon $created_at
 * @property-read User|null $actor
 */
class OrderStatusHistory extends Model
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
        return ['from_status' => OrderStatus::class, 'to_status' => OrderStatus::class, 'source' => TransitionSource::class];
    }
}
