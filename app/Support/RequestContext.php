<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/** Per-request correlation id, shared with logs, API envelopes, and audit rows. */
final class RequestContext
{
    private ?string $requestId = null;

    public function requestId(): string
    {
        return $this->requestId ??= (string) Str::ulid();
    }

    public function reset(): void
    {
        $this->requestId = null;
    }
}
