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
 * A person's statement that an item was posted on Instagram, on which revision and when. Immutable.
 *
 * @property int $id
 * @property string $public_id
 * @property int $content_item_id
 * @property int $content_revision_id
 * @property Carbon $published_at
 * @property string|null $url
 * @property string|null $note
 * @property list<string>|null $checklist the posting checklist confirmed (phase 7)
 * @property int $actor_user_id
 * @property Carbon $created_at
 * @property-read ContentRevision $revision
 * @property-read User $actor
 */
class ContentPublication extends Model
{
    use AppendOnly, HasUlids;

    public const null UPDATED_AT = null;

    protected $guarded = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<ContentRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ContentRevision::class, 'content_revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'checklist' => 'array'];
    }
}
