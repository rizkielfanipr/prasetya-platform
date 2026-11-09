<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use Database\Factories\ContentBrandFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property int|null $current_profile_id
 * @property int|null $current_knowledge_version_id
 * @property-read ContentBrandProfile|null $currentProfile
 * @property-read ContentBrandKnowledgeVersion|null $currentKnowledge
 */
class ContentBrand extends Model
{
    /** @use HasFactory<ContentBrandFactory> */
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

    /** @return BelongsTo<ContentBrandProfile, $this> */
    public function currentProfile(): BelongsTo
    {
        return $this->belongsTo(ContentBrandProfile::class, 'current_profile_id');
    }

    /** @return HasMany<ContentBrandProfile, $this> */
    public function profiles(): HasMany
    {
        return $this->hasMany(ContentBrandProfile::class)->orderByDesc('version');
    }

    /** @return BelongsTo<ContentBrandKnowledgeVersion, $this> */
    public function currentKnowledge(): BelongsTo
    {
        return $this->belongsTo(ContentBrandKnowledgeVersion::class, 'current_knowledge_version_id');
    }

    /** @return HasMany<ContentBrandKnowledgeVersion, $this> */
    public function knowledgeVersions(): HasMany
    {
        return $this->hasMany(ContentBrandKnowledgeVersion::class)->orderByDesc('version');
    }

    /** @return HasMany<ContentPillar, $this> */
    public function pillars(): HasMany
    {
        return $this->hasMany(ContentPillar::class)->orderByDesc('is_active')->orderBy('name');
    }

    /** @return HasMany<ContentCampaign, $this> */
    public function campaigns(): HasMany
    {
        return $this->hasMany(ContentCampaign::class)->orderByDesc('is_active')->orderByDesc('starts_on');
    }

    /** @return HasMany<ContentFact, $this> */
    public function facts(): HasMany
    {
        return $this->hasMany(ContentFact::class)->orderByRaw('retired_at is not null')->orderByDesc('id');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
