<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One version of an item's content. Editable until it is submitted for review; from then on it is evidence and never
 * changes: a later edit is a new revision.
 *
 * @property int $id
 * @property string $public_id
 * @property int $content_item_id
 * @property int $number
 * @property string|null $objective
 * @property string|null $audience
 * @property string|null $brief
 * @property string|null $caption
 * @property string|null $hashtags
 * @property string|null $alt_text
 * @property string|null $visual_direction
 * @property int $author_user_id
 * @property int|null $submitted_by_user_id
 * @property Carbon|null $submitted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ContentItem $item
 * @property-read ContentAsset|null $asset
 * @property-read User $author
 * @property-read User|null $submitter
 */
class ContentRevision extends Model
{
    use HasUlids;

    /** The editable content fields, in form order. */
    public const array FIELDS = ['objective', 'audience', 'brief', 'caption', 'hashtags', 'alt_text', 'visual_direction'];

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(static function (self $revision): void {
            // Setting submitted_at is the last allowed change.
            if ($revision->getOriginal('submitted_at') !== null) {
                throw new LogicException('A submitted content revision is immutable.');
            }
        });
        static::deleting(static fn () => throw new LogicException('Content revisions are never deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /** @return BelongsTo<ContentItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class, 'content_item_id');
    }

    /** @return HasOne<ContentAsset, $this> */
    public function asset(): HasOne
    {
        return $this->hasOne(ContentAsset::class);
    }

    /** @return HasMany<ContentApproval, $this> */
    public function decisions(): HasMany
    {
        return $this->hasMany(ContentApproval::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'number' => 'integer'];
    }
}
