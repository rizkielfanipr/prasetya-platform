<?php

declare(strict_types=1);

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\Data\InboundNotification;
use App\Domain\Payments\Data\VerifiedNotification;
use App\Domain\Payments\Enums\WebhookRejection;

interface NotificationVerifier
{
    /** Returns the rejection reason, or null when the delivery is authentic. */
    public function rejectionFor(InboundNotification $notification): ?WebhookRejection;

    /** Parses an authentic body. Null means it is authentic but unusable. */
    public function parse(string $rawBody): ?VerifiedNotification;
}
