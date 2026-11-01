<?php

declare(strict_types=1);

namespace App\Domain\Payments\Enums;

enum WebhookStatus: string
{
    case Received = 'RECEIVED';
    case Verified = 'VERIFIED';
    case Processed = 'PROCESSED';
    /** Authentic but deliberately causes no change, e.g. a Checkout FAILED notification. */
    case Ignored = 'IGNORED';
    case Duplicate = 'DUPLICATE';
    case Rejected = 'REJECTED';
    case Failed = 'FAILED';
}
