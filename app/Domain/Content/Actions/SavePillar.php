<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentPillar;
use Illuminate\Support\Facades\DB;

/** Adds or changes a content pillar. The target shares of a brand's active pillars add up to at most 100%. */
final class SavePillar
{
    public function __construct(private readonly RecordAudit $audit) {}

    public function __invoke(ContentBrand $brand, ?ContentPillar $pillar, string $name, ?string $description, int $targetShare, bool $isActive, User $actor): ContentPillar
    {
        return DB::transaction(function () use ($brand, $pillar, $name, $description, $targetShare, $isActive, $actor): ContentPillar {
            ContentBrand::query()->lockForUpdate()->findOrFail($brand->id);
            if ($pillar !== null && $pillar->content_brand_id !== $brand->id) {
                throw ContentActionRefused::because('Pilar ini milik brand lain.');
            }
            $duplicate = ContentPillar::query()->where('content_brand_id', $brand->id)->where('name', $name)
                ->when($pillar !== null, fn ($q) => $q->whereKeyNot($pillar?->id))->exists();
            if ($duplicate) {
                throw ContentActionRefused::because("Pilar \"{$name}\" sudah ada.");
            }
            $others = (int) ContentPillar::query()->where('content_brand_id', $brand->id)->where('is_active', true)
                ->when($pillar !== null, fn ($q) => $q->whereKeyNot($pillar?->id))->sum('target_share');
            if ($isActive && $others + $targetShare > 100) {
                throw ContentActionRefused::because('Total target pilar aktif lebih dari 100%. Sisa yang tersedia: '.max(0, 100 - $others).'%.');
            }

            $pillar ??= new ContentPillar(['content_brand_id' => $brand->id]);
            $previous = $pillar->exists ? $pillar->only(['name', 'description', 'target_share', 'is_active']) : null;
            $pillar->fill(['name' => $name, 'description' => $description, 'target_share' => $targetShare, 'is_active' => $isActive]);
            if ($pillar->exists && ! $pillar->isDirty()) {
                return $pillar;
            }
            $pillar->save();

            ($this->audit)($previous === null ? 'content.pillar_created' : 'content.pillar_updated', 'content_brand', $brand->public_id, $actor,
                $previous === null ? null : array_diff_assoc($previous, $pillar->only(array_keys($previous))),
                ['pillar' => $pillar->name] + ($previous === null ? $pillar->only(['target_share', 'is_active']) : array_diff_assoc($pillar->only(array_keys($previous)), $previous)),
            );

            return $pillar;
        });
    }
}
