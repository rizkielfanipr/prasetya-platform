<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Exceptions\InvalidBrandGuide;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentBrandKnowledgeVersion;
use App\Domain\Content\Support\BrandKnowledgeChunker;
use App\Domain\Content\Support\PostingRulesSection;
use Illuminate\Support\Facades\DB;

/**
 * Saves the one Brand Knowledge editor as an immutable version and deterministic retrieval index. When the document
 * holds an `## Aturan Posting` section, its rules become a new rules version if they changed (ADR-0017); without the
 * section the rules in force stay as they are.
 */
final class SaveBrandKnowledge
{
    public function __construct(
        private readonly BrandKnowledgeChunker $chunker,
        private readonly RecordAudit $audit,
        private readonly PostingRulesSection $section,
        private readonly UpdateBrandProfile $updateRules,
    ) {}

    public function __invoke(ContentBrand $brand, string $source, User $actor): ContentBrandKnowledgeVersion
    {
        $source = trim(str_replace("\r\n", "\n", $source));
        $chunks = $this->chunker->chunks($source);
        if ($chunks === []) {
            throw ContentActionRefused::because('Brand Knowledge belum berisi informasi yang dapat disimpan.');
        }
        $body = $this->section->extract($source);
        try {
            $rules = $body === null ? null : $this->section->parse($body);
        } catch (InvalidBrandGuide $invalid) {
            throw ContentActionRefused::because($invalid->getMessage());
        }

        return DB::transaction(function () use ($brand, $source, $chunks, $rules, $actor): ContentBrandKnowledgeVersion {
            $brand = ContentBrand::query()->lockForUpdate()->with('currentKnowledge')->findOrFail($brand->id);
            $current = $brand->currentKnowledge;
            $nextVersion = $current === null ? 1 : $current->version + 1;
            if ($current?->source_text === $source) {
                throw ContentActionRefused::because('Tidak ada Brand Knowledge yang berubah.');
            }
            $version = ContentBrandKnowledgeVersion::query()->create([
                'content_brand_id' => $brand->id,
                'version' => $nextVersion,
                'source_text' => $source,
                'created_by_user_id' => $actor->id,
            ]);
            $version->chunks()->createMany(array_map(static fn (string $content, int $position): array => [
                'position' => $position + 1,
                'content' => $content,
            ], $chunks, array_keys($chunks)));
            $brand->forceFill(['current_knowledge_version_id' => $version->id])->save();
            ($this->audit)('content.brand_knowledge_updated', 'content_brand', $brand->public_id, $actor,
                $current === null ? null : ['version' => $current->version],
                ['version' => $version->version, 'chunks' => count($chunks)],
            );

            if ($rules !== null) {
                $this->applyRules($brand, $rules, $version, $actor);
            }

            return $version;
        });
    }

    /**
     * A new rules version only when the section's rules differ from those in force. Fields the section does not hold
     * (audience, voice, visual guidance, preferred terms, examples) carry forward unchanged.
     *
     * @param  array<string, mixed>  $rules
     */
    private function applyRules(ContentBrand $brand, array $rules, ContentBrandKnowledgeVersion $version, User $actor): void
    {
        $current = $brand->currentProfile()->first()?->rules() ?? [];
        $changed = array_filter($rules, static fn (mixed $value, string $key): bool => ($current[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH);
        if ($changed === [] && $current !== []) {
            return;
        }

        ($this->updateRules)($brand, [...$current, ...$rules], "Dari Brand Knowledge versi {$version->version}", $actor);
    }
}
