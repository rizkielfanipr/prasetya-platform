<?php

declare(strict_types=1);

namespace App\Http\Workspace;

use App\Domain\Payments\Enums\ReconciliationGroup;

/**
 * The review queues of Transaksi → Perlu Ditinjau (ADR-0016). Display only: each queue is a fixed set of the domain's
 * reconciliation groups, whose queries and rules stay in ReconciliationReport.
 */
enum AttentionQueue: string
{
    case Unmatched = 'unmatched';
    case AmountMismatch = 'amount_mismatch';
    case Waiting = 'waiting';
    case System = 'system';

    /** @return list<ReconciliationGroup> */
    public function groups(): array
    {
        return match ($this) {
            self::Unmatched => [ReconciliationGroup::UnmatchedPayments],
            self::AmountMismatch => [ReconciliationGroup::AmountMismatch],
            self::Waiting => [ReconciliationGroup::OrdersWithoutSuccess],
            self::System => [ReconciliationGroup::PaidInconsistent, ReconciliationGroup::InvalidTransitions, ReconciliationGroup::RejectedWebhooks],
        };
    }

    /** Listed only when it has items: the system queue is normally empty and is not daily work. */
    public function hiddenWhenEmpty(): bool
    {
        return $this === self::System;
    }

    public static function forGroup(ReconciliationGroup $group): ?self
    {
        foreach (self::cases() as $queue) {
            if (in_array($group, $queue->groups(), true)) {
                return $queue;
            }
        }

        return null;
    }

    /**
     * Where an old `?group=` link lands: the queue holding the group, or the new home of an informational group.
     *
     * @return array{route: string, query: array<string, string>}
     */
    public static function destinationFor(ReconciliationGroup $group): array
    {
        return match ($group) {
            ReconciliationGroup::OrdersWithoutAttempt => ['route' => 'transactions.index', 'query' => ['payment_status' => 'NONE']],
            ReconciliationGroup::DuplicateWebhooks => ['route' => 'settings.gateway-log', 'query' => ['status' => 'DUPLICATE']],
            ReconciliationGroup::PendingTooLong => ['route' => 'transactions.index', 'query' => ['view' => 'attention', 'queue' => self::Waiting->value]],
            default => ['route' => 'transactions.index', 'query' => ['view' => 'attention', 'queue' => (self::forGroup($group) ?? self::Waiting)->value]],
        };
    }
}
