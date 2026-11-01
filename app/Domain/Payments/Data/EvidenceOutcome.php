<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

use App\Domain\Payments\Enums\WebhookRejection;
use App\Domain\Payments\Enums\WebhookStatus;

final readonly class EvidenceOutcome
{
    public function __construct(public WebhookStatus $status, public ?WebhookRejection $rejection = null) {}
}
