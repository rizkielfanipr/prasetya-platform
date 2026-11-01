<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use App\Domain\Orders\Enums\TransitionSource;
use App\Domain\Payments\Enums\PaymentEventType;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Support\Eloquent\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payment_id
 * @property int|null $webhook_event_id
 * @property PaymentEventType $type
 * @property TransitionSource $source
 * @property PaymentStatus|null $from_status
 * @property PaymentStatus|null $to_status
 * @property array<string, mixed>|null $metadata
 * @property string|null $request_id
 * @property Carbon $occurred_at
 */
class PaymentEvent extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => PaymentEventType::class,
            'source' => TransitionSource::class,
            'from_status' => PaymentStatus::class,
            'to_status' => PaymentStatus::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
