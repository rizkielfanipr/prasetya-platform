<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Content\Enums\ContentStatus;
use Database\Factories\ContentItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One planned single-image Instagram post. Status changes only through App\Domain\Content\Actions.
 *
 * @property int $id
 * @property string $public_id
 * @property int $content_brand_id
 * @property string $title
 * @property Carbon $planned_at
 * @property ContentStatus $status
 * @property int|null $active_revision_id
 * @property int|null $approved_revision_id
 * @property int $created_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ContentBrand $brand
 * @property-read ContentRevision|null $activeRevision
 * @property-read ContentPublication|null $publication
 * @property-read User $creator
 */
class ContentItem extends Model
{
    /** @use HasFactory<ContentItemFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<ContentBrand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(ContentBrand::class, 'content_brand_id');
    }

    /** @return BelongsTo<ContentRevision, $this> */
    public function activeRevision(): BelongsTo
    {
        return $this->belongsTo(ContentRevision::class, 'active_revision_id');
    }

    /** @return HasMany<ContentRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ContentRevision::class)->orderByDesc('number');
    }

    /** @return HasOne<ContentPublication, $this> */
    public function publication(): HasOne
    {
        return $this->hasOne(ContentPublication::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** True only while the current revision is the one that was approved. */
    public function isApprovedOnCurrentRevision(): bool
    {
        return $this->status === ContentStatus::Approved
            && $this->approved_revision_id !== null
            && $this->approved_revision_id === $this->active_revision_id;
    }

    protected function casts(): array
    {
        return ['planned_at' => 'datetime', 'status' => ContentStatus::class];
    }
}
