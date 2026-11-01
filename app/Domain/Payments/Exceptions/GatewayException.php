<?php

declare(strict_types=1);

namespace App\Domain\Payments\Exceptions;

use RuntimeException;

/** Safe to log: messages never contain secrets or customer data. */
class GatewayException extends RuntimeException
{
    public function __construct(string $message, public readonly string $failureCode = 'GATEWAY_ERROR')
    {
        parent::__construct($message);
    }
}
