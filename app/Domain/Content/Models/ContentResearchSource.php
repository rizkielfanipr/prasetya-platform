<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Content\Enums\SourceOrigin;
use App\Domain\Content\Enums\SourceTrust;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A web page used as research material: canonical URL, title, domain, access time, a short summary, trust, and
 * relevance. Never the full article. Untrusted text: it is material for people and AI, not instructions or facts.
 *
 * @property int $id
 * @property string $public_id
 * @property int $content_brand_id
 * @property int|null $content_research_run_id
 * @property SourceOrigin $origin
 * @property string $url
 * @property string $url_hash
 * @property string $domain
 * @property string $title
 * @property string|null $summary
 * @property SourceTrust $trust
 * @property string|null $relevance
 * @property Carbon|null $published_on
 * @property Carbon $fetched_at
 * @property int $added_by_user_id
 * @property-read User $addedBy
 */
class ContentResearchSource extends Model
{
    use HasUlids;

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

    /** @return BelongsTo<User, $this> */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'origin' => SourceOrigin::class,
            'trust' => SourceTrust::class,
            'relevance' => 'decimal:3',
            'published_on' => 'date',
            'fetched_at' => 'datetime',
        ];
    }
}
