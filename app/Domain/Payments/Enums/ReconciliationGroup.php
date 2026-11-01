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

    /** Duplicates are handled safely by design: shown for inspection, not counted as needing action. */
    public function needsAction(): bool
    {
        return $this !== self::DuplicateWebhooks;
    }
}
