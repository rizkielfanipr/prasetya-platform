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
 * Operational metadata for one AI-assisted Brand Knowledge interview. It deliberately stores no answers or output.
 *
 * @property int $id
 * @property string $public_id
 * @property int $content_brand_id
 * @property int $requested_by_user_id
 * @property GenerationStatus $status
 * @property string $provider
 * @property string|null $model
 * @property string $prompt_version
 * @property int $input_tokens
 * @property int $output_tokens
 * @property string $cost_usd
 * @property int|null $latency_ms
 * @property string|null $error_code
 * @property string|null $error_message
 * @property string|null $provider_request_id
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property-read ContentBrand $brand
 * @property-read User $requester
 */
final class ContentBrandKnowledgeAssist extends Model
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

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'cost_usd' => 'decimal:6',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
