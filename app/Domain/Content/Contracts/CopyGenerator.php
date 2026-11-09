<?php

declare(strict_types=1);

namespace App\Domain\Content\Contracts;

use App\Domain\Content\Ai\CopyPrompt;
use App\Domain\Content\Ai\CopyProviderFailed;
use App\Domain\Content\Ai\CopyResponse;

/**
 * Writes draft copy from a prompt. Adapters live in App\Integrations; the domain never sees an SDK, a URL, or a key.
 * The answer is untrusted text: GeneratedCopy validates it before anything is stored.
 */
interface CopyGenerator
{
    /** Short provider name for records, e.g. "openai_compatible" or "fake". */
    public function provider(): string;

    public function model(): ?string;

    /** Why the provider cannot be used, or null when it can. Never contains a secret. */
    public function unavailableReason(): ?string;

    /** @throws CopyProviderFailed */
    public function generate(CopyPrompt $prompt): CopyResponse;
}
