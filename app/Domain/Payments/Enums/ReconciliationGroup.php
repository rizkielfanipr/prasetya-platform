<?php

declare(strict_types=1);

namespace App\Domain\Payments\Enums;

enum ReconciliationGroup: string
{
    case OrdersWithoutAttempt = 'orders_without_attempt';
    case OrdersWithoutSuccess = 'orders_without_success';
    case UnmatchedPayments = 'unmatched_payments';
    case AmountMismatch = 'amount_mismatch';
    case RejectedWebhooks = 'rejected_webhooks';
    case DuplicateWebhooks = 'duplicate_webhooks';
    case PendingTooLong = 'pending_too_long';
    case PaidInconsistent = 'paid_inconsistent';
    case InvalidTransitions = 'invalid_transitions';

    /**
     * ADR-0016: six groups ask a person to decide something. The other three are information, still checked and shown
     * on the transaction: duplicates are handled safely by design, an order without an attempt is a customer who did
     * not check out, and an open payment past the threshold is already counted once through its waiting order.
     */
    public function needsAction(): bool
    {
        return ! in_array($this, [self::DuplicateWebhooks, self::OrdersWithoutAttempt, self::PendingTooLong], true);
    }
}
