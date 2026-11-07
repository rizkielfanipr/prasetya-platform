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
 * Which workflow actions a user may take on an item now, and why not. The actions enforce exactly these answers; the
 * presenter sends them to the screen so a disabled button can say why (ADR-0011).
 */
final class ContentAvailability
{
    public function __construct(private readonly ContentRuleValidator $rules) {}

    public const array ACTIONS = ['edit', 'editDetails', 'submit', 'requestRevision', 'approve', 'reject', 'markPublished'];

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
            'edit' => match ($status) {
                ContentStatus::ReadyForReview => 'Konten sedang ditinjau. Minta revisi dulu untuk mengubahnya.',
                ContentStatus::Published => 'Konten sudah dipublikasikan dan tidak dapat diubah.',
                ContentStatus::Rejected => 'Konten ditolak dan tidak dapat diubah.',
                default => null,
            },
            'editDetails' => $status->isFinal() ? 'Judul dan jadwal tidak dapat diubah setelah konten '.($status === ContentStatus::Published ? 'dipublikasikan.' : 'ditolak.') : null,
            'submit' => match (true) {
                ! in_array($status, [ContentStatus::Draft, ContentStatus::RevisionRequested], true) => 'Hanya draft atau konten yang diminta revisi yang dapat diajukan.',
                $status === ContentStatus::RevisionRequested && $revision->isSubmitted() => 'Ubah konten sesuai catatan revisi sebelum mengajukan lagi.',
                default => $this->notReady($item, $revision),
            },
            'requestRevision' => $status === ContentStatus::ReadyForReview ? null : 'Revisi hanya dapat diminta saat konten sedang ditinjau.',
            'approve' => match (true) {
                $status !== ContentStatus::ReadyForReview => 'Hanya konten yang sedang ditinjau yang dapat disetujui.',
                $revision->submitted_by_user_id === $viewer->id => 'Revisi ini Anda ajukan sendiri. Pengguna lain harus menyetujuinya.',
                default => null,
            },
            'reject' => in_array($status, [ContentStatus::Draft, ContentStatus::ReadyForReview], true) ? null : 'Hanya draft atau konten yang sedang ditinjau yang dapat ditolak.',
            'markPublished' => match (true) {
                $status === ContentStatus::Published => 'Konten sudah ditandai dipublikasikan.',
                ! $item->isApprovedOnCurrentRevision() => 'Hanya revisi terbaru yang sudah disetujui yang dapat ditandai dipublikasikan.',
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

    /** Why a revision cannot go to review yet: missing content first, then brand-rule errors. */
    public function notReady(ContentItem $item, ContentRevision $revision): ?string
    {
        $missing = $this->missing($revision);
        if ($missing !== null) {
            return $missing;
        }
        $errors = ContentRuleValidator::errors($this->rules->check($item, $revision));

        return $errors === 0 ? null : "Perbaiki {$errors} pelanggaran aturan brand sebelum mengajukan.";
    }

    /** What a revision still lacks before review, as one sentence, or null when it is complete. */
    public function missing(ContentRevision $revision): ?string
    {
        $missing = array_keys(array_filter([
            'brief' => blank($revision->brief),
            'caption' => blank($revision->caption),
            'alt text' => blank($revision->alt_text),
            'gambar' => $revision->asset === null,
        ]));

        return $missing === [] ? null : 'Lengkapi '.$this->list($missing).' sebelum mengajukan.';
    }

    /** @param  list<string>  $items */
    private function list(array $items): string
    {
        $last = array_pop($items);

        return $items === [] ? (string) $last : implode(', ', $items).' dan '.$last;
    }
}
