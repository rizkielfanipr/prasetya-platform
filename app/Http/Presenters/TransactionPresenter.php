<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Orders\Models\AdminNote;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderStatusHistory;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use App\Domain\Payments\Models\WebhookEvent;

/** The back-office contract for transactions; resources/js/types/backoffice.ts mirrors these shapes. */
final class TransactionPresenter
{
    /** @return array<string, mixed> */
    public static function row(Order $order): array
    {
        $payment = $order->latestPayment;

        return [
            'id' => $order->public_id,
            'invoice' => $order->invoice_number,
            'brand' => $order->application->code->value,
            'customerName' => $order->customer_name,
            'packageName' => $order->items->first()?->package_name,
            'amount' => $order->total_amount,
            'paymentStatus' => $payment?->status->value,
            'orderStatus' => $order->status->value,
            'paymentMethod' => $payment?->payment_method,
            'createdAt' => $order->created_at->toIso8601ZuluString(),
            'lastPaymentUpdate' => $payment?->last_event_at?->toIso8601ZuluString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Order $order): array
    {
        $order->loadMissing(['application', 'customer', 'items', 'latestPayment', 'payments.events', 'statusHistories.actor', 'adminNotes.author', 'webworksDetail', 'invitationDetail']);

        return [
            ...self::row($order),
            'expiresAt' => $order->expires_at?->toIso8601ZuluString(),
            'confirmedAt' => $order->confirmed_at?->toIso8601ZuluString(),
            'customer' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
            ],
            'items' => $order->items->map(static fn ($item): array => [
                'packageCode' => $item->package_code,
                'packageName' => $item->package_name,
                'quantity' => $item->quantity,
                'unitAmount' => $item->unit_amount,
                'lineAmount' => $item->line_amount,
            ])->all(),
            'brandDetails' => self::brandDetails($order),
            'payments' => $order->payments->map(static fn (Payment $payment): array => self::payment($payment))->all(),
            'statusHistory' => $order->statusHistories->map(static fn (OrderStatusHistory $history): array => [
                'id' => $history->id,
                'from' => $history->from_status?->value,
                'to' => $history->to_status->value,
                'source' => $history->source->value,
                'actor' => $history->actor?->name,
                'reason' => $history->reason,
                'at' => $history->created_at->toIso8601ZuluString(),
            ])->all(),
            'notes' => $order->adminNotes->map(static fn (AdminNote $note): array => [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $note->author->name,
                'at' => $note->created_at->toIso8601ZuluString(),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    public static function payment(Payment $payment): array
    {
        return [
            'id' => $payment->public_id,
            'attempt' => $payment->attempt_number,
            'provider' => $payment->provider,
            'status' => $payment->status->value,
            'amount' => $payment->amount,
            'gatewayInvoice' => $payment->gateway_invoice_number,
            'gatewayReference' => $payment->gateway_reference,
            'paymentMethod' => $payment->payment_method,
            'failureCode' => $payment->failure_code,
            'expiresAt' => $payment->expires_at?->toIso8601ZuluString(),
            'paidAt' => $payment->paid_at?->toIso8601ZuluString(),
            'createdAt' => $payment->created_at->toIso8601ZuluString(),
            'events' => $payment->events->map(static fn (PaymentEvent $event): array => [
                'id' => $event->id,
                'type' => $event->type->value,
                'source' => $event->source->value,
                'from' => $event->from_status?->value,
                'to' => $event->to_status?->value,
                'at' => $event->occurred_at->toIso8601ZuluString(),
                'detail' => self::eventDetail($event),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    public static function webhook(WebhookEvent $event): array
    {
        return [
            'id' => $event->public_id,
            'provider' => $event->provider,
            'invoice' => $event->invoice_number,
            'orderId' => $event->payment?->order?->public_id,
            'eventType' => $event->transaction_status,
            'channel' => $event->channel,
            'amount' => $event->amount,
            'signatureValid' => $event->signature_valid,
            'status' => $event->status->value,
            'isDuplicate' => $event->duplicate_of_id !== null,
            'rejectionReason' => $event->rejection_reason?->value,
            'requestId' => $event->request_id,
            'receivedAt' => $event->received_at->toIso8601ZuluString(),
            'processedAt' => $event->processed_at?->toIso8601ZuluString(),
            'diagnostics' => $event->sanitized_payload,
        ];
    }

    /** @return array<string, mixed> */
    public static function audit(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'actor' => $log->actor_label,
            'action' => $log->action,
            'entityType' => $log->entity_type,
            'entityId' => $log->entity_id,
            'previousState' => $log->previous_state,
            'newState' => $log->new_state,
            'reason' => $log->reason,
            'requestId' => $log->request_id,
            'at' => $log->created_at->toIso8601ZuluString(),
        ];
    }

    private static function eventDetail(PaymentEvent $event): ?string
    {
        $metadata = $event->metadata ?? [];
        $parts = array_filter([
            isset($metadata['gateway_status']) ? 'Status gateway: '.$metadata['gateway_status'] : null,
            isset($metadata['reason']) ? 'Alasan: '.$metadata['reason'] : null,
            isset($metadata['failure_code']) ? 'Kode: '.$metadata['failure_code'] : null,
            isset($metadata['channel']) ? 'Kanal: '.$metadata['channel'] : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /** @return list<array{label: string, value: string}> */
    private static function brandDetails(Order $order): array
    {
        $rows = [];

        if ($order->webworksDetail !== null) {
            $detail = $order->webworksDetail;
            $rows = [
                ['label' => 'Template', 'value' => $detail->template_code],
                ['label' => 'Nama bisnis', 'value' => $detail->business_name],
                ['label' => 'Preferensi domain', 'value' => $detail->domain_preference],
                ['label' => 'Catatan pelanggan', 'value' => $detail->notes],
            ];
        }

        if ($order->invitationDetail !== null) {
            $detail = $order->invitationDetail;
            $rows = [
                ['label' => 'Tema', 'value' => $detail->theme_code],
                ['label' => 'Jenis acara', 'value' => $detail->event_type],
                ['label' => 'Tanggal acara', 'value' => $detail->event_date?->format('Y-m-d')],
                ['label' => 'Catatan pelanggan', 'value' => $detail->notes],
            ];
        }

        return array_values(array_filter($rows, static fn (array $row): bool => filled($row['value'])));
    }
}
