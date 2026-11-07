<?php

declare(strict_types=1);

namespace App\Domain\Content\Ai;

final readonly class CopyPrompt
{
    public function __construct(
        public string $system,
        public string $user,
        public int $maxOutputTokens,
        public string $version,
    ) {}

    /** A rough upper estimate of input tokens (about three characters per token for Indonesian and JSON). */
    public function estimatedInputTokens(): int
    {
        return (int) ceil((mb_strlen($this->system) + mb_strlen($this->user)) / 3);
    }
}
