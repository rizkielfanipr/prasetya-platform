<?php

declare(strict_types=1);

namespace App\Domain\Content\Research;

/** One untrusted search result as the provider gave it. Checked by UrlPolicy before anything is stored. */
final readonly class SearchHit
{
    public function __construct(
        public string $url,
        public string $title,
        public string $snippet,
        public ?float $score = null,
        public ?string $publishedDate = null,
    ) {}
}
