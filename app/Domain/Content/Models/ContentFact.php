<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * An approved business fact (price, product name, promo, period) that content may state. The statement never changes:
 * correcting a fact retires it and adds a new one. Only retirement may be recorded later.
 *
 * @property int $id
 * @property string $public_id
 * @property int $content_brand_id
 * @property int|null $content_campaign_id
 * @property string $statement
 * @property string $source
 * @property Carbon|null $valid_until
 * @property int $created_by_user_id
 * @property Carbon|null $retired_at
 * @property int|null $retired_by_user_id
 * @property string|null $retired_reason
 * @property Carbon $created_at
 * @property-read ContentCampaign|null $campaign
 * @property-read User $creator
 * @property-read User|null $retiredBy
 */
class ContentFact extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(static function (self $fact): void {
            if (array_diff(array_keys($fact->getDirty()), ['retired_at', 'retired_by_user_id', 'retired_reason', 'updated_at']) !== [] || $fact->getOriginal('retired_at') !== null) {
                throw new LogicException('A content fact is only ever retired, never edited.');
            }
        });
        static::deleting(static fn () => throw new LogicException('Content facts are never deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Facts content may rely on on a given business day: not retired and not expired.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUsableOn(Builder $query, string $day): void
    {
        $query->whereNull('retired_at')->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $day));
    }

    /** @return BelongsTo<ContentCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ContentCampaign::class, 'content_campaign_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function retiredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retired_by_user_id');
    }

    protected function casts(): array
    {
        return ['valid_until' => 'date', 'retired_at' => 'datetime'];
    }
}
