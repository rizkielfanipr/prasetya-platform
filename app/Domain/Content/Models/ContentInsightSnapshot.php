<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Auth\Models\User;
use App\Support\Eloquent\AppendOnly;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Metrics read from a published post's Instagram insights at one moment, entered by a person. Append-only.
 *
 * @property int $id
 * @property string $public_id
 * @property int $content_item_id
 * @property Carbon $measured_at
 * @property int $reach
 * @property int|null $views
 * @property int|null $likes
 * @property int|null $comments
 * @property int|null $saves
 * @property int|null $shares
 * @property int|null $profile_visits
 * @property int|null $follows
 * @property string|null $note
 * @property int $recorded_by_user_id
 * @property Carbon $created_at
 * @property-read User $recordedBy
 */
class ContentInsightSnapshot extends Model
{
    use AppendOnly, HasUlids;

    public const null UPDATED_AT = null;

    /** The approved metrics, in display order. Reach is required; the rest are optional. */
    public const array METRICS = ['reach', 'views', 'likes', 'comments', 'saves', 'shares', 'profile_visits', 'follows'];

    protected $guarded = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** Likes, comments, saves, and shares per person reached; null when nothing was reached. */
    public function engagementRate(): ?float
    {
        if ($this->reach === 0) {
            return null;
        }

        return round(((int) $this->likes + (int) $this->comments + (int) $this->saves + (int) $this->shares) / $this->reach, 4);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    protected function casts(): array
    {
        return ['measured_at' => 'datetime'] + array_fill_keys(self::METRICS, 'integer');
    }
}
