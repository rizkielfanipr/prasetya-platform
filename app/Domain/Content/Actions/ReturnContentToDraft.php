<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\ContentAvailability;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Support\ContentItemLock;
use Illuminate\Support\Facades\DB;

/** Takes a Ready item back to Draft (ADR-0012). The frozen revision stays as history; the next edit starts a new one. */
final class ReturnContentToDraft
{
    public function __construct(
        private readonly RecordAudit $audit,
        private readonly ContentAvailability $availability,
        private readonly ContentItemLock $lock,
    ) {}

    public function __invoke(ContentItem $item, string $expectedRevision, User $actor): void
    {
        DB::transaction(function () use ($item, $expectedRevision, $actor): void {
            $item = $this->lock->lock($item, $expectedRevision);
            $this->availability->ensure($item, $actor, 'returnToDraft');
            $revision = $item->activeRevision?->number;

            $item->forceFill(['status' => ContentStatus::Draft, 'ready_revision_id' => null])->save();

            ($this->audit)('content.returned_to_draft', 'content_item', $item->public_id, $actor,
                ['status' => ContentStatus::Ready->value],
                ['status' => ContentStatus::Draft->value, 'revision' => $revision],
            );
        });
    }
}
