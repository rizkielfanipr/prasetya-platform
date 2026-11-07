<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Content\Enums\ResearchStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One web search: the query, outcome, how many results were kept or dropped by policy, and estimated cost.
 *
 * @property int $id
 * @property string $public_id
 * @property int $content_brand_id
 * @property int|null $content_item_id
 * @property string $query
 * @property ResearchStatus $status
 * @property string $provider
 * @property int $max_results
 * @property int $results_count
 * @property int $dropped_count
 * @property list<int>|null $result_source_ids
 * @property int $search_calls
 * @property string $cost_usd
 * @property int|null $latency_ms
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int $requested_by_user_id
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property-read ContentBrand $brand
 * @property-read ContentItem|null $item
 * @property-read User $requester
 */
class ContentResearchRun extends Model
{
    use HasUlids;

    protected $guarded = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<ContentBrand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(ContentBrand::class, 'content_brand_id');
    }

    /** @return BelongsTo<ContentItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class, 'content_item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return HasMany<ContentResearchSource, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(ContentResearchSource::class)->orderByDesc('relevance');
    }

    protected function casts(): array
    {
        return ['result_source_ids' => 'array', 'status' => ResearchStatus::class, 'cost_usd' => 'decimal:6', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
