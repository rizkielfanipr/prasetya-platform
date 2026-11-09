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

/**
 * Cancels a planned post without deleting anything (ADR-0012): revisions, sources, AI runs, legacy decisions, and
 * audit all stay. Archived is terminal. The optional reason is kept in the audit entry.
 */
final class ArchiveContent
{
    public function __construct(
        private readonly RecordAudit $audit,
        private readonly ContentAvailability $availability,
        private readonly ContentItemLock $lock,
    ) {}

    public function __invoke(ContentItem $item, string $expectedRevision, ?string $reason, User $actor): void
    {
        $reason = $reason === null || trim($reason) === '' ? null : mb_substr(trim($reason), 0, 500);

        DB::transaction(function () use ($item, $expectedRevision, $reason, $actor): void {
            $item = $this->lock->lock($item, $expectedRevision);
            $this->availability->ensure($item, $actor, 'archive');
            $previous = $item->status;

            $item->forceFill(['status' => ContentStatus::Archived, 'ready_revision_id' => null])->save();

            ($this->audit)('content.archived', 'content_item', $item->public_id, $actor,
                ['status' => $previous->value],
                ['status' => ContentStatus::Archived->value, 'revision' => $item->activeRevision?->number],
                $reason,
            );
        });
    }
}
