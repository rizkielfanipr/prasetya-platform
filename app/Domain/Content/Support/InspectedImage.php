<?php

declare(strict_types=1);

namespace App\Domain\Content\Support;

/** What the bytes of an accepted upload say it is. */
final readonly class InspectedImage
{
    public function __construct(
        public string $mimeType,
        public string $extension,
        public int $width,
        public int $height,
        public int $sizeBytes,
        public string $checksum,
    ) {}
}
