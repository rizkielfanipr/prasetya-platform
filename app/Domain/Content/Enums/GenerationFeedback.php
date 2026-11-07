<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

/** A person's verdict on an AI draft, kept for evaluating providers and prompts. It never changes a prompt by itself. */
enum GenerationFeedback: string
{
    case Useful = 'USEFUL';
    case NotUseful = 'NOT_USEFUL';
}
