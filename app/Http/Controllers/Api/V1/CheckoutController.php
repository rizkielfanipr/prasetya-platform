<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Actions\StartCheckout;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Support\Api\ApiResponse;
use App\Support\Idempotency\IdempotencyGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CheckoutController
{
    public function __invoke(Request $request, Order $order, StartCheckout $startCheckout, IdempotencyGate $gate): JsonResponse
    {
        $result = $gate->run("orders.checkout:{$order->public_id}", $request->header('Idempotency-Key'), hash('sha256', $order->public_id), static function () use ($order, $startCheckout): array {
            return ['id' => $startCheckout($order)->public_id, 'status' => 201];
        });

        $payment = Payment::query()->where('public_id', $result['id'])->firstOrFail();

        return ApiResponse::success([
            'order_reference' => $order->public_id,
            'payment_reference' => $payment->public_id,
            'payment_status' => $payment->status->value,
            'checkout_url' => $payment->status === PaymentStatus::Pending ? $payment->checkout_url : null,
            'expires_at' => $payment->expires_at?->toIso8601ZuluString(),
        ], $result['status'], ['idempotentReplay' => $result['replayed']]);
    }
}
