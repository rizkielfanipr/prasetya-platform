<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
final class PaymentStatusResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $payment = $this->latestPayment;

        return [
            'order_reference' => $this->public_id,
            'invoice_number' => $this->invoice_number,
            'order_status' => $this->status->value,
            'payment_status' => $payment?->status->value,
            'paid_at' => $payment?->paid_at?->toIso8601ZuluString(),
            'expires_at' => $payment?->expires_at?->toIso8601ZuluString(),
            'checkout_url' => $payment?->status === PaymentStatus::Pending ? $payment->checkout_url : null,
        ];
    }
}
