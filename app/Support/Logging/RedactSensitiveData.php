<?php

declare(strict_types=1);

namespace App\Support\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

/** Monolog tap: applied to log channels in config/logging.php. */
final class RedactSensitiveData
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(fn (LogRecord $record): LogRecord => $record->with(
            context: Redactor::redact($record->context),
            extra: Redactor::redact($record->extra),
        ));
    }
}
