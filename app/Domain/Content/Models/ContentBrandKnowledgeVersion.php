<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Auth\Models\User;
use App\Support\Eloquent\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One immutable source document. Saving the single editor creates a version and replaces the active retrieval index.
 *
 * @property int $id
 * @property int $content_brand_id
 * @property int $version
 * @property string $source_text
 * @property int $created_by_user_id
 * @property Carbon $created_at
 * @property-read User $creator
 */
final class ContentBrandKnowledgeVersion extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    protected $guarded = [];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<ContentBrandKnowledgeChunk, $this> */
    public function chunks(): HasMany
    {
        return $this->hasMany(ContentBrandKnowledgeChunk::class, 'knowledge_version_id')->orderBy('position');
    }
}
