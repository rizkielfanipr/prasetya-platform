<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

/** Human-facing knowledge groups; they organise approved facts but never change their authority. */
enum ContentFactCategory: string
{
    case General = 'GENERAL';
    case Service = 'SERVICE';
    case Price = 'PRICE';
    case Process = 'PROCESS';
    case Advantage = 'ADVANTAGE';
    case Faq = 'FAQ';
    case Promo = 'PROMO';
    case Policy = 'POLICY';
    case Evidence = 'EVIDENCE';
}
