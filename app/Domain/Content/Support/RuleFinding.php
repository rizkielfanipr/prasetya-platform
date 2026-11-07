<?php

declare(strict_types=1);

namespace App\Domain\Content\Support;

use App\Domain\Content\Enums\RuleSeverity;

/** One result of checking content against its brand's rules. */
final readonly class RuleFinding
{
    public function __construct(
        public string $code,
        public RuleSeverity $severity,
        public string $message,
        public ?string $field = null,
    ) {}

    /** @return array{code: string, severity: string, message: string, field: string|null} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'severity' => $this->severity->value, 'message' => $this->message, 'field' => $this->field];
    }
}
