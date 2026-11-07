<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Content\Enums\ContentDecision;
use App\Support\Eloquent\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only review decision on exactly one revision.
 *
 * @property int $id
 * @property int $content_revision_id
 * @property ContentDecision $decision
 * @property list<string>|null $areas parts a revision request names
 * @property string|null $note
 * @property int $actor_user_id
 * @property Carbon $created_at
 * @property-read ContentRevision $revision
 * @property-read User $actor
 */
class ContentApproval extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    protected $guarded = [];

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
        return ['decision' => ContentDecision::class, 'areas' => 'array'];
    }
}
