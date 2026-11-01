<?php

declare(strict_types=1);

namespace App\Domain\Payments\Enums;

enum PaymentEventType: string
{
    case AttemptCreated = 'ATTEMPT_CREATED';
    case CheckoutOpened = 'CHECKOUT_OPENED';
    case CheckoutFailed = 'CHECKOUT_FAILED';
    case StatusChanged = 'STATUS_CHANGED';
    case EvidenceIgnored = 'EVIDENCE_IGNORED';
    case EvidenceRejected = 'EVIDENCE_REJECTED';
    case GatewayChecked = 'GATEWAY_CHECKED';
}
