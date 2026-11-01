<?php

declare(strict_types=1);

namespace App\Domain\Auth\Enums;

enum Permission: string
{
    case ViewTransactions = 'transactions.view';
    case AddOrderNotes = 'orders.notes.add';
    case ViewPaymentEvents = 'payment-events.view';
    case ViewReconciliation = 'reconciliation.view';
    case RunGatewayCheck = 'reconciliation.gateway-check';
    case ViewAuditLog = 'audit-log.view';
    case ManageSettings = 'settings.manage';
}
