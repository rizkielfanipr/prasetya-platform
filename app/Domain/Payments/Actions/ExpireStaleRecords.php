<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Orders\Actions\TransitionOrderStatus;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\TransitionSource;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;

/** Scheduled. A late verified SUCCESS can still move these to PAID and CONFIRMED afterwards. */
final class ExpireStaleRecords
{
    public function __construct(
        private readonly TransitionPaymentStatus $transitionPayment,
        private readonly TransitionOrderStatus $transitionOrder,
    ) {}

    /** @return array{payments: int, orders: int} */
    public function __invoke(): array
    {
        $paymentCutoff = now()->subMinutes((int) config('platform.payment_expiry_grace_minutes'));
        $counts = ['payments' => 0, 'orders' => 0];

        Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->where('expires_at', '<', $paymentCutoff)
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use (&$counts): void {
                DB::transaction(function () use ($id, &$counts): void {
                    $payment = Payment::query()->lockForUpdate()->findOrFail($id);

                    if ($payment->status === PaymentStatus::Pending) {
                        ($this->transitionPayment)($payment, PaymentStatus::Expired, TransitionSource::Scheduler);
                        $counts['payments']++;
                    }
                });
            });

        Order::query()
            ->where('status', OrderStatus::PendingPayment)
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use (&$counts): void {
                DB::transaction(function () use ($id, &$counts): void {
                    $order = Order::query()->lockForUpdate()->findOrFail($id);
                    $hasOpenAttempt = Payment::query()->where('order_id', $id)->where('status', PaymentStatus::Pending)->exists();

                    if ($order->status === OrderStatus::PendingPayment && ! $hasOpenAttempt) {
                        ($this->transitionOrder)($order, OrderStatus::Expired, TransitionSource::Scheduler);
                        $counts['orders']++;
                    }
                });
            });

        return $counts;
    }
}
