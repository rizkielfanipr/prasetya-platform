<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\ContentAvailability;
use App\Domain\Content\Enums\ContentDecision;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentApproval;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentPublication;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Support\ContentItemLock;
use App\Domain\Content\Support\PostingQueue;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records a person's statement that the approved, current revision was posted on Instagram (ADR-0011, decision 8).
 * The only way an item becomes PUBLISHED; nothing is posted by the platform.
 */
final class MarkContentPublished
{
    /** Tolerated clock difference between the user's device and the server. */
    private const int FUTURE_SKEW_MINUTES = 5;

    public function __construct(
        private readonly RecordAudit $audit,
        private readonly ContentAvailability $availability,
        private readonly ContentItemLock $lock,
    ) {}

    /** @param  list<string>  $checklist  the PostingQueue::CHECKLIST steps the person confirms */
    public function __invoke(ContentItem $item, string $expectedRevision, CarbonInterface $publishedAt, ?string $url, ?string $note, User $actor, array $checklist = PostingQueue::CHECKLIST): ContentPublication
    {
        $checklist = array_values(array_intersect(PostingQueue::CHECKLIST, $checklist));
        if (count($checklist) !== count(PostingQueue::CHECKLIST)) {
            throw ContentActionRefused::because('Centang semua langkah checklist posting sebelum menandai dipublikasikan.');
        }
        if ($publishedAt->isAfter(now()->addMinutes(self::FUTURE_SKEW_MINUTES))) {
            throw ContentActionRefused::because('Waktu posting tidak boleh di masa depan. Tandai setelah konten benar-benar diposting.');
        }

        return DB::transaction(function () use ($item, $expectedRevision, $publishedAt, $url, $note, $actor, $checklist): ContentPublication {
            $item = $this->lock->lock($item, $expectedRevision);
            $this->availability->ensure($item, $actor, 'markPublished');
            /** @var ContentRevision $revision */
            $revision = $item->activeRevision;

            $approvedAt = ContentApproval::query()
                ->where('content_revision_id', $revision->id)
                ->where('decision', ContentDecision::Approved)
                ->latest('id')
                ->value('created_at');
            if ($approvedAt !== null && $publishedAt->isBefore(Carbon::parse($approvedAt)->startOfMinute())) {
                throw ContentActionRefused::because('Waktu posting tidak boleh sebelum revisi ini disetujui.');
            }

            $publication = ContentPublication::query()->create([
                'content_item_id' => $item->id,
                'content_revision_id' => $revision->id,
                'published_at' => $publishedAt,
                'url' => $url === null || trim($url) === '' ? null : trim($url),
                'note' => $note === null || trim($note) === '' ? null : trim($note),
                'checklist' => $checklist,
                'actor_user_id' => $actor->id,
            ]);
            $item->forceFill(['status' => ContentStatus::Published])->save();

            ($this->audit)('content.published', 'content_item', $item->public_id, $actor,
                ['status' => ContentStatus::Approved->value],
                [
                    'status' => ContentStatus::Published->value,
                    'revision' => $revision->number,
                    'published_at' => $publication->published_at->toIso8601ZuluString(),
                    'url' => $publication->url,
                    'checklist' => $checklist,
                ],
                $publication->note,
            );

            return $publication;
        });
    }
}
