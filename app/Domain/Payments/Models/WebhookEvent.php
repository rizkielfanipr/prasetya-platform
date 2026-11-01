<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\WebhookRejection;
use App\Domain\Payments\Enums\WebhookStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Evidence of one delivery. Only processing outcome columns change after insert.
 *
 * @property int $id
 * @property string $public_id
 * @property string $provider
 * @property string|null $provider_request_id
 * @property WebhookStatus $status
 * @property WebhookRejection|null $rejection_reason
 * @property bool $signature_valid
 * @property string|null $dedupe_key
 * @property string|null $primary_dedupe_key
 * @property int|null $duplicate_of_id
 * @property string|null $invoice_number
 * @property int|null $amount
 * @property string|null $transaction_status
 * @property string|null $channel
 * @property int|null $payment_id
 * @property string $payload_digest
 * @property array<string, mixed>|null $sanitized_payload
 * @property string|null $request_id
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 * @property-read Payment|null $payment
 */
class WebhookEvent extends Model
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

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    protected function casts(): array
    {
        return [
            'status' => WebhookStatus::class,
            'rejection_reason' => WebhookRejection::class,
            'signature_valid' => 'boolean',
            'amount' => 'integer',
            'sanitized_payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
