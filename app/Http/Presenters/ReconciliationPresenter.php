<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\ReconciliationGroup;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Model;

/** Reconciliation groups come from three models; this gives the page one row shape. */
final class ReconciliationPresenter
{
    /** @return array<string, mixed> */
    public static function row(Model $record): array
    {
        return match (true) {
            $record instanceof Order => [
                'key' => 'order-'.$record->public_id,
                'invoice' => $record->invoice_number,
                'brand' => $record->application->code->value,
                'amount' => $record->total_amount,
                'orderStatus' => $record->status->value,
                'paymentStatus' => $record->latestPayment?->status->value,
                'detail' => null,
                'orderId' => $record->public_id,
                'checkablePaymentId' => self::checkable($record->latestPayment),
                'at' => $record->created_at->toIso8601ZuluString(),
            ],
            $record instanceof Payment => [
                'key' => 'payment-'.$record->public_id,
                'invoice' => $record->gateway_invoice_number,
                'brand' => $record->order->application->code->value,
                'amount' => $record->amount,
                'orderStatus' => $record->order->status->value,
                'paymentStatus' => $record->status->value,
                'detail' => null,
                'orderId' => $record->order->public_id,
                'checkablePaymentId' => self::checkable($record),
                'at' => $record->created_at->toIso8601ZuluString(),
            ],
            $record instanceof WebhookEvent => [
                'key' => 'webhook-'.$record->public_id,
                'invoice' => $record->invoice_number,
                'brand' => $record->payment?->order->application->code->value,
                'amount' => $record->amount,
                'orderStatus' => $record->payment?->order->status->value,
                'paymentStatus' => $record->payment?->status->value,
                'detail' => $record->rejection_reason->value ?? $record->status->value,
                'orderId' => $record->payment?->order->public_id,
                'checkablePaymentId' => self::checkable($record->payment),
                'at' => $record->received_at->toIso8601ZuluString(),
            ],
            default => throw new \LogicException('Unsupported reconciliation record.'),
        };
    }

    /**
     * An issue of one transaction, with its group so the page can explain it and say whether it needs action.
     *
     * @return array<string, mixed>
     */
    public static function scoped(ReconciliationGroup $group, Model $record): array
    {
        $row = self::row($record);

        return [...$row, 'key' => $group->value.'-'.$row['key'], 'group' => $group->value, 'needsAction' => $group->needsAction()];
    }

    /** Only payments not already PAID: a check on a settled payment could not change anything. */
    private static function checkable(?Payment $payment): ?string
    {
        return $payment !== null && $payment->status !== PaymentStatus::Paid ? $payment->public_id : null;
    }
}
