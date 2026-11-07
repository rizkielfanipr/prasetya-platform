<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

/** A part of a post a reviewer can ask to change. Copy parts map onto what AI may redraft. */
enum RevisionArea: string
{
    case Brief = 'brief';
    case Caption = 'caption';
    case Hashtags = 'hashtags';
    case AltText = 'alt_text';
    case VisualDirection = 'visual_direction';
    case Image = 'image';

    public function copyField(): ?CopyField
    {
        return CopyField::tryFrom($this->value);
    }
}
