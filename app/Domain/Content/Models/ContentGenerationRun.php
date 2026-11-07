<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Content\Enums\GenerationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One AI copy generation for an item: what was asked, by whom, the outcome, usage, and estimated cost.
 *
 * @property int $id
 * @property string $public_id
 * @property int $content_item_id
 * @property int $base_revision_id
 * @property int|null $result_revision_id
 * @property int $requested_by_user_id
 * @property list<string> $fields
 * @property GenerationStatus $status
 * @property string $provider
 * @property string|null $model
 * @property string $prompt_version
 * @property int $input_tokens
 * @property int $output_tokens
 * @property string $cost_usd
 * @property int|null $latency_ms
 * @property int $attempts
 * @property string|null $error_code
 * @property string|null $error_message
 * @property string|null $provider_request_id
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property-read ContentItem $item
 * @property-read ContentRevision $baseRevision
 * @property-read ContentRevision|null $resultRevision
 * @property-read User $requester
 */
class ContentGenerationRun extends Model
{
    use HasUlids;

    protected $guarded = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<ContentItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class, 'content_item_id');
    }

    /** @return BelongsTo<ContentRevision, $this> */
    public function baseRevision(): BelongsTo
    {
        return $this->belongsTo(ContentRevision::class, 'base_revision_id');
    }

    /** @return BelongsTo<ContentRevision, $this> */
    public function resultRevision(): BelongsTo
    {
        return $this->belongsTo(ContentRevision::class, 'result_revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'status' => GenerationStatus::class,
            'cost_usd' => 'decimal:6',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
