<?php

declare(strict_types=1);

namespace App\Domain\Content\Contracts;

use App\Domain\Content\Research\SearchFailed;

/** Reads a public page's title and description, for a manual source with no title. SSRF-safe by contract. */
interface PageReader
{
    /**
     * @return array{title: string|null, description: string|null, url: string} url is the final URL after safe redirects
     *
     * @throws SearchFailed
     */
    public function read(string $url): array;
}
