<?php

declare(strict_types=1);

use App\Domain\Auth\Models\User;
use App\Domain\Content\Actions\CreateContentItem;
use App\Domain\Content\Actions\MarkContentPublished;
use App\Domain\Content\Actions\MarkContentReady;
use App\Domain\Content\Actions\SaveContentRevision;
use App\Domain\Content\Actions\UpdateBrandProfile;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentBrandProfile;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Support\ContentPlan;
use App\Domain\Content\Support\ContentRuleValidator;
use App\Domain\Content\Support\RuleFinding;
use Database\Seeders\ContentBrandSeeder;
use Database\Seeders\Support\DemoImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * Content Studio test helpers (ADR-0011, ADR-0012). Current states are reached through the real actions, never by
 * setting a status. Only legacyContentFixture() writes rows directly, because the actions that made those shapes are gone.
 */

function contentSetUp(): void
{
    test()->seed(ContentBrandSeeder::class);
    Storage::fake((string) config('content.disk'));
}

function webworks(): ContentBrand
{
    return ContentBrand::query()->where('code', 'WEBWORKS')->firstOrFail();
}

/** @param  array{int, int, int}  $rgb */
function postImage(int $width = 1080, int $height = 1350, array $rgb = [10, 20, 30], string $name = 'post.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, DemoImage::png($width, $height, $rgb));
}

/** @return array<string, string> */
function completeContent(array $overrides = []): array
{
    return [
        'objective' => 'Edukasi pemilik usaha.',
        'audience' => 'Pemilik UMKM.',
        'brief' => 'Satu gambar dengan judul besar.',
        'caption' => 'Website yang rapi membantu pelanggan percaya.',
        'hashtags' => '#websiteumkm #bisnisonline',
        'alt_text' => 'Kartu oranye bertuliskan judul posting.',
        'visual_direction' => 'Latar polos, tipografi tebal.',
        ...$overrides,
    ];
}

function draftItem(?User $writer = null, bool $complete = true, string $title = 'Kenapa website lambat merugikan'): ContentItem
{
    $writer ??= admin();
    $item = app(CreateContentItem::class)(webworks(), new ContentPlan($title, now()->addDay()), $writer);
    if ($complete) {
        $image = postImage();
        app(SaveContentRevision::class)($item, revisionId($item), completeContent(), ['path' => (string) $image->getRealPath(), 'name' => 'post.png'], $writer);
    }

    return $item->refresh();
}

function readyItem(?User $writer = null): ContentItem
{
    $writer ??= admin();
    $item = draftItem($writer);
    app(MarkContentReady::class)($item, revisionId($item), $writer);

    return $item->refresh();
}

function publishedItem(?User $writer = null): ContentItem
{
    $writer ??= admin();
    $item = readyItem($writer);
    app(MarkContentPublished::class)($item, revisionId($item), now(), null, null, $writer);

    return $item->refresh();
}

function revisionId(ContentItem $item): string
{
    return (string) $item->refresh()->activeRevision?->public_id;
}

/** @return array<string, mixed> */
function contentPage(string $uri, ?User $viewer = null): array
{
    return test()->actingAs($viewer ?? admin())->get($uri)->assertOk()->viewData('page')['props'];
}

/**
 * One evidence sheet of an item's detail page (ADR-0013), as the browser loads it: `research`, `history`, or `activity`.
 *
 * @return array<string, mixed>
 */
function contentEvidence(ContentItem $item, string $sheet, ?User $viewer = null): array
{
    return test()->actingAs($viewer ?? admin())->getJson("/content/items/{$item->public_id}/{$sheet}")->assertOk()->json();
}

/** @param  array<string, mixed>  $rules */
function brandRules(array $rules = [], string $note = 'Aturan uji'): ContentBrandProfile
{
    $empty = array_fill_keys(ContentBrandProfile::LISTS, []);

    return app(UpdateBrandProfile::class)(webworks()->refresh(), [...$empty, ...$rules], $note, admin());
}

/** @return list<string> finding codes for an item's current revision */
function findingCodes(ContentItem $item): array
{
    $item->refresh();

    return array_map(
        static fn (RuleFinding $f): string => $f->code,
        app(ContentRuleValidator::class)->check($item, $item->activeRevision),
    );
}

/**
 * One item in every legacy editorial status, as ADR-0011 left it in the database (ADR-0012 migration rehearsal).
 * Each starts from a frozen revision made by the real actions; the legacy status, approval rows, and
 * approved_revision_id are then written the way the retired review actions wrote them, with ready_revision_id unset.
 *
 * @return array<string, ContentItem> legacy status => item
 */
function legacyContentFixture(): array
{
    $writer = admin();
    $legacy = static function (ContentItem $item, string $status, ?string $decision = null, ?string $note = null, ?array $areas = null) use ($writer): ContentItem {
        $revision = (int) $item->active_revision_id;
        if ($decision !== null) {
            DB::table('content_approvals')->insert([
                'content_revision_id' => $revision, 'decision' => $decision, 'areas' => $areas === null ? null : json_encode($areas),
                'note' => $note, 'actor_user_id' => $writer->id, 'created_at' => now(),
            ]);
        }
        DB::table('content_items')->where('id', $item->id)->update([
            'status' => $status,
            'ready_revision_id' => null,
            'approved_revision_id' => in_array($status, ['APPROVED', 'PUBLISHED'], true) ? $revision : null,
        ]);

        return $item->refresh();
    };

    return [
        'DRAFT' => $legacy(draftItem($writer), 'DRAFT'),
        'READY_FOR_REVIEW' => $legacy(readyItem($writer), 'READY_FOR_REVIEW'),
        'REVISION_REQUESTED' => $legacy(readyItem($writer), 'REVISION_REQUESTED', 'REVISION_REQUESTED', 'Ringkas caption', ['caption']),
        'APPROVED' => $legacy(readyItem($writer), 'APPROVED', 'APPROVED'),
        'PUBLISHED' => $legacy(publishedItem($writer), 'PUBLISHED', 'APPROVED'),
        'REJECTED' => $legacy(readyItem($writer), 'REJECTED', 'REJECTED', 'Tidak sesuai kampanye'),
    ];
}
