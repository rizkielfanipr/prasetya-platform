<?php

declare(strict_types=1);

namespace App\Domain\Content\Ai;

/** A copy model that may be used, with its USD price per million tokens. */
final readonly class CopyModel
{
    public function __construct(
        public string $name,
        public float $priceInputPerMillion,
        public float $priceOutputPerMillion,
    ) {}
}
