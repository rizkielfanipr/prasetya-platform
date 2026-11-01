<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $order_id
 * @property int $attempt_number
 * @property string $provider
 * @property PaymentStatus $status
 * @property int $amount
 * @property string $currency
 * @property string $gateway_invoice_number
 * @property string $gateway_request_id
 * @property string|null $gateway_reference
 * @property string|null $checkout_url
 * @property string|null $payment_method
 * @property string|null $failure_code
 * @property Carbon|null $expires_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $last_event_at
 * @property Carbon $created_at
 * @property-read Order $order
 * @property-read Collection<int, PaymentEvent> $events
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
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

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<PaymentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'attempt_number' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'last_event_at' => 'datetime',
        ];
    }
}
