<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Auth\Models\User;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Support\ContentRuleValidator;

/**
 * Which workflow actions a person may take on an item now, and why not (ADR-0012). The actions enforce exactly these
 * answers; the presenter sends them to the screen so a disabled button can say why. There is no reviewer: the same
 * signed-in person drafts, marks Ready, and records the post.
 */
final class ContentAvailability
{
    public function __construct(private readonly ContentRuleValidator $rules) {}

    public const array ACTIONS = ['edit', 'editDetails', 'markReady', 'returnToDraft', 'archive', 'markPublished'];

    /** @return array<string, string|null> action => reason it is unavailable, or null when it is allowed */
    public function for(ContentItem $item, User $viewer): array
    {
        $result = [];
        foreach (self::ACTIONS as $action) {
            $result[$action] = $this->reason($item, $viewer, $action);
        }

        return $result;
    }

    /** @throws ContentActionRefused */
    public function ensure(ContentItem $item, User $actor, string $action): void
    {
        $reason = $this->reason($item, $actor, $action);
        if ($reason !== null) {
            throw ContentActionRefused::because($reason);
        }
    }

    public function reason(ContentItem $item, User $viewer, string $action): ?string
    {
        $status = $item->status;
        /** @var ContentRevision $revision */
        $revision = $item->activeRevision;

        return match ($action) {
            // Editing Ready content is allowed: it starts a new revision and returns the item to Draft.
            'edit', 'editDetails' => match ($status) {
                ContentStatus::Published => 'Konten sudah diposting dan tidak dapat diubah.',
                ContentStatus::Archived => 'Konten diarsipkan dan tidak dapat diubah.',
                default => null,
            },
            'markReady' => $status === ContentStatus::Draft ? $this->notReady($item, $revision) : 'Hanya Draft yang dapat ditandai siap posting.',
            'returnToDraft' => $status === ContentStatus::Ready ? null : 'Hanya konten Siap posting yang dapat dikembalikan ke Draft.',
            'archive' => in_array($status, [ContentStatus::Draft, ContentStatus::Ready], true) ? null : 'Hanya Draft atau konten Siap posting yang dapat diarsipkan.',
            'markPublished' => match (true) {
                $status === ContentStatus::Published => 'Konten sudah ditandai diposting.',
                ! $item->isReadyOnCurrentRevision() => 'Tandai siap posting dulu sebelum mencatat posting.',
                default => null,
            },
            default => throw new \InvalidArgumentException("Unknown content action [{$action}]."),
        };
    }

    /** @throws ContentActionRefused when the revision is incomplete or breaks a brand rule */
    public function ensureReady(ContentItem $item, ContentRevision $revision): void
    {
        $reason = $this->notReady($item, $revision);
        if ($reason !== null) {
            throw ContentActionRefused::because($reason);
        }
    }

    /** Why a revision cannot be marked Ready yet: missing content first, then blocking brand-rule errors. */
    public function notReady(ContentItem $item, ContentRevision $revision): ?string
    {
        $missing = $this->missing($revision);
        if ($missing !== null) {
            return $missing;
        }
        $errors = ContentRuleValidator::errors($this->rules->check($item, $revision));

        return $errors === 0 ? null : "Perbaiki {$errors} pelanggaran aturan brand sebelum menandai siap posting.";
    }

    /** What a revision still lacks before Ready, as one sentence, or null when it is complete. */
    public function missing(ContentRevision $revision): ?string
    {
        // Brief is needed for AI, not for Ready: a caption written by hand is enough (ADR-0012).
        $missing = array_keys(array_filter([
            'caption' => blank($revision->caption),
            'alt text' => blank($revision->alt_text),
            'gambar' => $revision->asset === null,
        ]));

        return $missing === [] ? null : 'Lengkapi '.$this->list($missing).' sebelum menandai siap posting.';
    }

    /** @param  list<string>  $items */
    private function list(array $items): string
    {
        $last = array_pop($items);

        return $items === [] ? (string) $last : implode(', ', $items).' dan '.$last;
    }
}
