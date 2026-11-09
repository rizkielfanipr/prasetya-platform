<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\ContentAvailability;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentAsset;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Support\ContentAssetStore;
use App\Domain\Content\Support\ContentImageInspector;
use App\Domain\Content\Support\ContentItemLock;
use App\Domain\Content\Support\RevisionCopier;
use Illuminate\Support\Facades\DB;

/**
 * Saves the post's content (ADR-0011, decision 6).
 *
 * - An open revision (a draft) is edited in place.
 * - A frozen revision (marked Ready, or submitted under the old editorial workflow) is never changed: the edit becomes
 *   a new revision that copies it.
 * - Editing Ready content therefore starts a new Draft revision and returns the item to Draft (ADR-0012).
 *
 * The image is inspected and stored before the transaction; files are content-addressed, so an aborted save leaves
 * at most an unreferenced file that a later upload of the same bytes reuses.
 */
final class SaveContentRevision
{
    public function __construct(
        private readonly RecordAudit $audit,
        private readonly ContentAvailability $availability,
        private readonly ContentItemLock $lock,
        private readonly ContentImageInspector $inspector,
        private readonly ContentAssetStore $store,
        private readonly RevisionCopier $copier,
    ) {}

    /**
     * @param  array<string, string|null>  $fields  values for ContentRevision::FIELDS; missing keys keep their value
     * @param  array{path: string, name: string}|null  $image
     */
    public function __invoke(ContentItem $item, string $expectedRevision, array $fields, ?array $image, User $actor): ContentRevision
    {
        $stored = null;
        if ($image !== null) {
            $inspected = $this->inspector->inspect($image['path']);
            $stored = ['image' => $inspected, 'path' => $this->store->put($image['path'], $inspected), 'name' => mb_substr(basename($image['name']), 0, 255)];
        }

        return DB::transaction(function () use ($item, $expectedRevision, $fields, $stored, $actor): ContentRevision {
            $item = $this->lock->lock($item, $expectedRevision);
            $this->availability->ensure($item, $actor, 'edit');
            /** @var ContentRevision $current */
            $current = $item->activeRevision;

            $values = [];
            foreach (ContentRevision::FIELDS as $field) {
                $values[$field] = array_key_exists($field, $fields) ? $this->clean($fields[$field]) : $current->{$field};
            }
            $changed = array_keys(array_filter($values, static fn (?string $value, string $field): bool => $value !== $current->{$field}, ARRAY_FILTER_USE_BOTH));
            $imageChanged = $stored !== null && $stored['image']->checksum !== $current->asset?->checksum_sha256;

            if ($changed === [] && ! $imageChanged) {
                if ($current->isSubmitted()) {
                    throw ContentActionRefused::because('Tidak ada perubahan untuk disimpan.');
                }

                return $current;
            }

            $wasReady = $item->status === ContentStatus::Ready;
            $revision = $current->isSubmitted() ? $this->copier->copy($item, $current, $actor) : $current;
            $revision->fill($values);
            $revision->save();

            if ($stored !== null && $imageChanged) {
                ContentAsset::query()->where('content_revision_id', $revision->id)->delete();
                ContentAsset::query()->create([
                    'content_revision_id' => $revision->id,
                    'disk' => $this->store->disk(),
                    'path' => $stored['path'],
                    'original_name' => $stored['name'],
                    'mime_type' => $stored['image']->mimeType,
                    'size_bytes' => $stored['image']->sizeBytes,
                    'width' => $stored['image']->width,
                    'height' => $stored['image']->height,
                    'checksum_sha256' => $stored['image']->checksum,
                    'uploaded_by_user_id' => $actor->id,
                ]);
            }

            $previousStatus = $item->status;
            $item->forceFill(['active_revision_id' => $revision->id]);
            if ($wasReady) {
                $item->forceFill(['status' => ContentStatus::Draft, 'ready_revision_id' => null]);
            }
            $item->save();

            ($this->audit)('content.revised', 'content_item', $item->public_id, $actor,
                ['revision' => $current->number, 'status' => $previousStatus->value],
                ['revision' => $revision->number, 'status' => $item->status->value, 'fields' => $changed, 'image_changed' => $imageChanged],
            );

            return $revision;
        });
    }

    private function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim(str_replace("\r\n", "\n", $value));

        return $value === '' ? null : $value;
    }
}
