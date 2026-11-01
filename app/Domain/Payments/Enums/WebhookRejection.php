<?php

declare(strict_types=1);

namespace App\Domain\Payments\Enums;

enum WebhookRejection: string
{
    case MissingHeaders = 'MISSING_HEADERS';
    case InvalidSignature = 'INVALID_SIGNATURE';
    case ClientMismatch = 'CLIENT_MISMATCH';
    case StaleTimestamp = 'STALE_TIMESTAMP';
    case MalformedPayload = 'MALFORMED_PAYLOAD';
    case UnknownInvoice = 'UNKNOWN_INVOICE';
    case AmountMismatch = 'AMOUNT_MISMATCH';
    case InvalidTransition = 'INVALID_TRANSITION';
    case UnsupportedStatus = 'UNSUPPORTED_STATUS';
}
