<?php

declare(strict_types=1);

namespace App\Support\Api;

use RuntimeException;

final class ApiException extends RuntimeException
{
    /** @param array<string, list<string>> $fields */
    public function __construct(
        public readonly ApiErrorCode $errorCode,
        string $message,
        public readonly int $status,
        public readonly array $fields = [],
    ) {
        parent::__construct($message);
    }
}
