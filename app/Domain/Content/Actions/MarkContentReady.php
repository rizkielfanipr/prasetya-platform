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

/**
 * A person declares the current revision finished and ready to post (ADR-0012). Not an approval and never done by
 * AI, a queue, or Hermes. The revision is frozen: a later edit becomes a new revision and returns the item to Draft.
 * Needs caption, alt text, and an image, and no blocking brand-rule finding.
 */
final class MarkContentReady
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
            $this->availability->ensure($item, $actor, 'markReady');
            /** @var ContentRevision $revision */
            $revision = $item->activeRevision;

            // Freezing reuses the immutability of submitted revisions, and records the brand rules it met. A revision
            // already frozen (Ready before, then returned to Draft unchanged, or submitted under ADR-0011) stays as it is.
            if (! $revision->isSubmitted()) {
                $revision->forceFill(['submitted_by_user_id' => $actor->id, 'submitted_at' => now(), 'brand_profile_id' => $item->brand->current_profile_id])->save();
            }
            $item->forceFill(['status' => ContentStatus::Ready, 'ready_revision_id' => $revision->id])->save();

            ($this->audit)('content.marked_ready', 'content_item', $item->public_id, $actor,
                ['status' => ContentStatus::Draft->value],
                ['status' => ContentStatus::Ready->value, 'revision' => $revision->number, 'brand_rules_version' => $item->brand->currentProfile?->version],
            );
        });
    }
}
