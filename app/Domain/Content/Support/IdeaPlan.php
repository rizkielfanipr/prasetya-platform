<?php

declare(strict_types=1);

namespace App\Domain\Content\Support;

use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentPillar;

/**
 * Deterministic help for creating an item from one idea (ADR-0012, phase 3): a working title and, only when it is
 * unambiguous, a pillar. No AI is involved, so creating a draft never waits on or fails because of a provider.
 */
final class IdeaPlan
{
    public const int TITLE_LENGTH = 80;

    /** The first line of the idea, whitespace collapsed, cut at a word boundary with an ellipsis when too long. */
    public static function title(string $idea): string
    {
        $line = trim((string) preg_replace('/\s+/u', ' ', strtok(trim($idea), "\n") ?: ''));
        if (mb_strlen($line) <= self::TITLE_LENGTH) {
            return $line;
        }
        $cut = mb_substr($line, 0, self::TITLE_LENGTH - 1);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > self::TITLE_LENGTH / 2) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, ' ,.;:-').'…';
    }

    /** The one active pillar of the brand whose name appears in the idea as whole words; null when none or several do. */
    public static function pillar(ContentBrand $brand, string $idea): ?ContentPillar
    {
        $matches = $brand->pillars()->where('is_active', true)->get()->filter(
            static fn (ContentPillar $pillar): bool => (bool) preg_match('/(?<![\p{L}\p{N}])'.preg_quote(mb_strtolower($pillar->name), '/').'(?![\p{L}\p{N}])/u', mb_strtolower($idea)),
        );

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
