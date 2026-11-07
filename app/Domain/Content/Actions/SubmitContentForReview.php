<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\ContentAvailability;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Support\ContentItemLock;
use Illuminate\Support\Facades\DB;

/** Submits the current revision for review. From here on that revision is immutable. */
final class SubmitContentForReview
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
            $this->availability->ensure($item, $actor, 'submit');
            /** @var ContentRevision $revision */
            $revision = $item->activeRevision;
            $previous = $item->status;

            // The brand rules it met, so a later change to the rules never rewrites why it passed.
            $revision->forceFill(['submitted_by_user_id' => $actor->id, 'submitted_at' => now(), 'brand_profile_id' => $item->brand->current_profile_id])->save();
            $item->forceFill(['status' => ContentStatus::ReadyForReview])->save();

            ($this->audit)('content.submitted', 'content_item', $item->public_id, $actor,
                ['status' => $previous->value],
                ['status' => ContentStatus::ReadyForReview->value, 'revision' => $revision->number, 'brand_rules_version' => $item->brand->currentProfile?->version],
            );
        });
    }
}
