<?php

declare(strict_types=1);

namespace App\Domain\Content\Ai;

final readonly class CopyResponse
{
    public function __construct(
        public string $content,
        public int $inputTokens,
        public int $outputTokens,
        public ?string $requestId = null,
    ) {}
}
