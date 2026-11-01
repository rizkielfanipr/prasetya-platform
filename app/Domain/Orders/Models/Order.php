<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use App\Domain\Brands\Models\Application;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Payments\Models\Payment;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $application_id
 * @property int $customer_id
 * @property string $invoice_number
 * @property OrderStatus $status
 * @property int $total_amount
 * @property string $currency
 * @property string $customer_name
 * @property string $customer_email
 * @property string|null $customer_phone
 * @property Carbon|null $expires_at
 * @property Carbon|null $confirmed_at
 * @property Carbon $created_at
 * @property-read Application $application
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, Payment> $payments
 * @property-read Payment|null $latestPayment
 * @property-read WebworksOrderDetail|null $webworksDetail
 * @property-read InvitationOrderDetail|null $invitationDetail
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
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

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('attempt_number');
    }

    /** @return HasOne<Payment, $this> */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->ofMany('attempt_number', 'max');
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    /** @return HasMany<AdminNote, $this> */
    public function adminNotes(): HasMany
    {
        return $this->hasMany(AdminNote::class)->latest('id');
    }

    /** @return HasOne<WebworksOrderDetail, $this> */
    public function webworksDetail(): HasOne
    {
        return $this->hasOne(WebworksOrderDetail::class);
    }

    /** @return HasOne<InvitationOrderDetail, $this> */
    public function invitationDetail(): HasOne
    {
        return $this->hasOne(InvitationOrderDetail::class);
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_amount' => 'integer',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }
}
