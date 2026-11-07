<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentBrandProfile;
use Illuminate\Support\Facades\DB;

/**
 * Saves a brand's rules as a new version (ADR-0011, phase 2). Earlier versions stay, so content checked against them
 * can be explained later. Rules only describe what content may say; they never change an item's status.
 */
final class UpdateBrandProfile
{
    public function __construct(private readonly RecordAudit $audit) {}

    /** @param  array<string, mixed>  $rules  values for ContentBrandProfile::TEXTS, LISTS, and NUMBERS */
    public function __invoke(ContentBrand $brand, array $rules, string $changeNote, User $actor): ContentBrandProfile
    {
        $values = $this->normalise($rules);
        foreach ([['caption_min_length', 'caption_max_length', 'Panjang caption'], ['hashtag_min', 'hashtag_max', 'Jumlah hashtag']] as [$min, $max, $label]) {
            if ($values[$min] !== null && $values[$max] !== null && $values[$min] > $values[$max]) {
                throw ContentActionRefused::because("{$label}: batas minimal lebih besar dari batas maksimal.");
            }
        }

        return DB::transaction(function () use ($brand, $values, $changeNote, $actor): ContentBrandProfile {
            $brand = ContentBrand::query()->lockForUpdate()->findOrFail($brand->id);
            $current = $brand->currentProfile;
            $previous = $current?->rules() ?? [];
            $changed = array_keys(array_filter($values, static fn (mixed $value, string $key): bool => ($previous[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));
            if ($current !== null && $changed === []) {
                throw ContentActionRefused::because('Tidak ada aturan yang berubah.');
            }

            $profile = ContentBrandProfile::query()->create([
                ...$values,
                'content_brand_id' => $brand->id,
                'version' => ($current->version ?? 0) + 1,
                'change_note' => $changeNote,
                'created_by_user_id' => $actor->id,
            ]);
            $brand->forceFill(['current_profile_id' => $profile->id])->save();

            ($this->audit)('content.brand_rules_updated', 'content_brand', $brand->public_id, $actor,
                $current === null ? null : ['version' => $current->version],
                ['version' => $profile->version, 'fields' => $changed],
                $changeNote,
            );

            return $profile;
        });
    }

    /**
     * Trimmed text or null, lists without blanks or duplicates, whole numbers or null.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function normalise(array $rules): array
    {
        $values = [];
        foreach (ContentBrandProfile::TEXTS as $key) {
            $text = trim((string) ($rules[$key] ?? ''));
            $values[$key] = $text === '' ? null : $text;
        }
        foreach (ContentBrandProfile::LISTS as $key) {
            $list = is_array($rules[$key] ?? null) ? $rules[$key] : [];
            $clean = array_map(static fn (mixed $entry): string => trim((string) $entry), $list);
            $values[$key] = array_values(array_unique(array_filter($clean, static fn (string $entry): bool => $entry !== '')));
        }
        foreach (ContentBrandProfile::NUMBERS as $key) {
            $number = $rules[$key] ?? null;
            $values[$key] = $number === null || $number === '' ? null : (int) $number;
        }

        return $values;
    }
}
