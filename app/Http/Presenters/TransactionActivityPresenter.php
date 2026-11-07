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
use Illuminate\Support\Carbon;

/**
 * One read-only timeline for a transaction (ADR-0010), assembled from sources that stay separate in the database:
 * order status history, payment events, webhook evidence, admin notes, and audit. Nothing is stored. Each entry
 * carries structured, sanitised data; resources/js/lib/activity.ts turns it into words. No raw payload, signature,
 * or diagnostics are included.
 */
final class TransactionActivityPresenter
{
    /**
     * Newest first. Ties on the same instant are broken by source and id so the order never changes between loads.
     *
     * @param  iterable<WebhookEvent>  $webhooks
     * @param  iterable<AuditLog>  $audit
     * @return list<array<string, mixed>>
     */
    public static function timeline(Order $order, iterable $webhooks, iterable $audit): array
    {
        $order->loadMissing(['statusHistories.actor', 'payments.events', 'adminNotes.author']);

        $entries = [
            ...$order->statusHistories->map(static fn (OrderStatusHistory $h): array => self::entry('order_status', $h->id, $h->created_at, $h->actor?->name, [
                'from' => $h->from_status?->value,
                'to' => $h->to_status->value,
                'via' => $h->source->value,
                'reason' => $h->reason,
            ])),
            ...$order->payments->flatMap(static fn (Payment $payment) => $payment->events->map(static fn (PaymentEvent $e): array => self::entry('payment_event', $e->id, $e->occurred_at, null, [
                'attempt' => $payment->attempt_number,
                'type' => $e->type->value,
                'from' => $e->from_status?->value,
                'to' => $e->to_status?->value,
                'via' => $e->source->value,
                'gatewayStatus' => $e->metadata['gateway_status'] ?? null,
                'failureCode' => $e->metadata['failure_code'] ?? null,
            ]))),
            ...collect($webhooks)->map(static fn (WebhookEvent $w): array => self::entry('webhook', $w->id, $w->received_at, null, [
                'invoice' => $w->invoice_number,
                'status' => $w->status->value,
                'rejection' => $w->rejection_reason?->value,
                'signatureValid' => $w->signature_valid,
                'isDuplicate' => $w->duplicate_of_id !== null,
                'channel' => $w->channel,
            ])),
            ...$order->adminNotes->map(static fn (AdminNote $n): array => self::entry('note', $n->id, $n->created_at, $n->author->name, ['body' => $n->body])),
            ...collect($audit)->map(static fn (AuditLog $a): array => self::entry('audit', $a->id, $a->created_at, $a->actor_label, [
                'action' => $a->action,
                'entityType' => $a->entity_type,
                'reason' => $a->reason,
            ])),
        ];

        usort($entries, static fn (array $a, array $b): int => [$b['at'], $a['source'], $b['sourceId']] <=> [$a['at'], $b['source'], $a['sourceId']]);

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function entry(string $source, int $id, Carbon $at, ?string $actor, array $data): array
    {
        return ['id' => "{$source}-{$id}", 'source' => $source, 'sourceId' => $id, 'at' => $at->toIso8601ZuluString('microsecond'), 'actor' => $actor, 'data' => $data];
    }
}
