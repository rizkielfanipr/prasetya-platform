<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentFact;
use Illuminate\Support\Facades\DB;

/** Retires a fact that is wrong or no longer true. It stays on record; content can no longer rely on it. */
final class RetireContentFact
{
    public function __construct(private readonly RecordAudit $audit) {}

    public function __invoke(ContentBrand $brand, ContentFact $fact, string $reason, User $actor): void
    {
        if ($fact->content_brand_id !== $brand->id) {
            throw ContentActionRefused::because('Fakta ini milik brand lain.');
        }

        DB::transaction(function () use ($brand, $fact, $reason, $actor): void {
            $fact = ContentFact::query()->lockForUpdate()->findOrFail($fact->id);
            if ($fact->retired_at !== null) {
                throw ContentActionRefused::because('Fakta ini sudah tidak berlaku.');
            }
            $fact->forceFill(['retired_at' => now(), 'retired_by_user_id' => $actor->id, 'retired_reason' => trim($reason)])->save();

            ($this->audit)('content.fact_retired', 'content_brand', $brand->public_id, $actor,
                ['fact' => $fact->public_id, 'statement' => $fact->statement],
                ['retired' => true],
                $fact->retired_reason,
            );
        });
    }
}
