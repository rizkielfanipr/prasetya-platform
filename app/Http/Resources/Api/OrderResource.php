<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Anyone holding the reference can read this, so it carries no customer contact details.
 *
 * @mixin Order
 */
final class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->public_id,
            'invoice_number' => $this->invoice_number,
            'application_code' => $this->application->code->value,
            'status' => $this->status->value,
            'total' => ['amount' => $this->total_amount, 'currency' => $this->currency],
            'items' => $this->items->map(static fn (OrderItem $item): array => [
                'package_code' => $item->package_code,
                'package_name' => $item->package_name,
                'quantity' => $item->quantity,
                'unit_amount' => $item->unit_amount,
                'line_amount' => $item->line_amount,
            ])->all(),
            'payment_status' => $this->latestPayment?->status->value,
            'expires_at' => $this->expires_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
