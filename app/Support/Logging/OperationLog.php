<?php

declare(strict_types=1);

namespace App\Support\Logging;

use Illuminate\Support\Facades\Log;

/** Structured operational log line: operation, result, duration, plus safe context. Request id comes from shared context. */
final class OperationLog
{
    /** @param array<string, mixed> $context */
    public static function record(string $operation, string $result, array $context = [], ?float $startedAt = null): void
    {
        $line = ['operation' => $operation, 'result' => $result, ...$context];

        if ($startedAt !== null) {
            $line['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
        }

        Log::channel('operations')->info($operation, $line);
    }
}
