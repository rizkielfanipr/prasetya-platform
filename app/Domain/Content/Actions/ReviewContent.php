<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\ContentAvailability;
use App\Domain\Content\Enums\ContentDecision;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Enums\RevisionArea;
use App\Domain\Content\Models\ContentApproval;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Support\ContentItemLock;
use Illuminate\Support\Facades\DB;

/**
 * A reviewer's decision on the current revision: approve, request a revision, or reject. Each decision is an
 * append-only record on that revision. Approval needs a user other than the submitter (ADR-0011, decision 7); a
 * revision request and a rejection need a note.
 */
final class ReviewContent
{
    public function __construct(
        private readonly RecordAudit $audit,
        private readonly ContentAvailability $availability,
        private readonly ContentItemLock $lock,
    ) {}

    /** @param  list<RevisionArea>  $areas  for a revision request: the parts to change */
    public function __invoke(ContentItem $item, string $expectedRevision, ContentDecision $decision, ?string $note, User $actor, array $areas = []): void
    {
        $areaValues = $decision === ContentDecision::RevisionRequested ? array_values(array_unique(array_map(static fn (RevisionArea $a): string => $a->value, $areas))) : [];
        [$action, $status, $auditAction] = match ($decision) {
            ContentDecision::Approved => ['approve', ContentStatus::Approved, 'content.approved'],
            ContentDecision::RevisionRequested => ['requestRevision', ContentStatus::RevisionRequested, 'content.revision_requested'],
            ContentDecision::Rejected => ['reject', ContentStatus::Rejected, 'content.rejected'],
        };
        $note = $note === null || trim($note) === '' ? null : trim($note);
        if ($note === null && $decision !== ContentDecision::Approved) {
            throw new \InvalidArgumentException('A revision request or rejection needs a note.');
        }

        DB::transaction(function () use ($item, $expectedRevision, $decision, $note, $actor, $action, $status, $auditAction, $areaValues): void {
            $item = $this->lock->lock($item, $expectedRevision);
            $this->availability->ensure($item, $actor, $action);
            /** @var ContentRevision $revision */
            $revision = $item->activeRevision;
            $previous = $item->status;

            ContentApproval::query()->create([
                'content_revision_id' => $revision->id,
                'decision' => $decision,
                'areas' => $areaValues === [] ? null : $areaValues,
                'note' => $note,
                'actor_user_id' => $actor->id,
            ]);
            $item->forceFill([
                'status' => $status,
                'approved_revision_id' => $decision === ContentDecision::Approved ? $revision->id : null,
            ])->save();

            ($this->audit)($auditAction, 'content_item', $item->public_id, $actor,
                ['status' => $previous->value],
                ['status' => $status->value, 'revision' => $revision->number, ...($areaValues === [] ? [] : ['areas' => $areaValues])],
                $note,
            );
        });
    }
}
