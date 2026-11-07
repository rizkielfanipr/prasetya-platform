<?php

declare(strict_types=1);

namespace App\Domain\Content\Ai;

use RuntimeException;

/**
 * A provider call that did not produce usable copy. The message is safe to store and show: adapters never put a key,
 * header, or raw response body in it.
 */
final class CopyProviderFailed extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly bool $transient,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
    ) {
        parent::__construct($message);
    }

    public static function invalidOutput(string $message, int $inputTokens = 0, int $outputTokens = 0): self
    {
        return new self('invalid_output', $message, true, $inputTokens, $outputTokens);
    }
}
