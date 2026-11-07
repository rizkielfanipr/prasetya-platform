<?php

declare(strict_types=1);

namespace App\Integrations\ContentAi;

use App\Domain\Content\Ai\CopyPrompt;
use App\Domain\Content\Ai\CopyProviderFailed;
use App\Domain\Content\Ai\CopyResponse;
use App\Domain\Content\Contracts\CopyGenerator;

/** CONTENT_TEXT_PROVIDER=none (the default): AI copy is off and the manual workflow is unaffected. */
final class UnconfiguredCopyGenerator implements CopyGenerator
{
    public function provider(): string
    {
        return 'none';
    }

    public function model(): ?string
    {
        return null;
    }

    public function unavailableReason(): string
    {
        return 'AI belum dikonfigurasi. Konten tetap dapat ditulis manual.';
    }

    public function generate(CopyPrompt $prompt): CopyResponse
    {
        throw new CopyProviderFailed('unconfigured', 'AI belum dikonfigurasi.', false);
    }
}
