<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

/** The parts of a revision AI may draft. Brief, objective, and audience stay human: they are the instructions. */
enum CopyField: string
{
    case Caption = 'caption';
    case Hashtags = 'hashtags';
    case AltText = 'alt_text';
    case VisualDirection = 'visual_direction';

    public function maxLength(): int
    {
        return match ($this) {
            self::Caption => 2200,
            self::Hashtags, self::AltText => 1000,
            self::VisualDirection => 3000,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Caption => 'caption',
            self::Hashtags => 'hashtag',
            self::AltText => 'alt text',
            self::VisualDirection => 'arahan visual',
        };
    }
}
