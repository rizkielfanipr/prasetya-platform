<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

/** An error blocks submission for review; a warning is shown to the writer and the reviewer. */
enum RuleSeverity: string
{
    case Error = 'ERROR';
    case Warning = 'WARNING';
}
