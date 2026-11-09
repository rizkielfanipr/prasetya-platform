<?php

declare(strict_types=1);

use App\Domain\Auth\Models\User;
use App\Domain\Content\Actions\CreateContentItem;
use App\Domain\Content\Actions\MarkContentPublished;
use App\Domain\Content\Actions\ReviewContent;
use App\Domain\Content\Actions\SaveContentRevision;
use App\Domain\Content\Actions\SubmitContentForReview;
use App\Domain\Content\Actions\UpdateBrandProfile;
use App\Domain\Content\Enums\ContentDecision;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentBrandProfile;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Support\ContentPlan;
use App\Domain\Content\Support\ContentRuleValidator;
use App\Domain\Content\Support\RuleFinding;
use Database\Seeders\ContentBrandSeeder;
use Database\Seeders\Support\DemoImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/* Content Studio test helpers (ADR-0011). States are reached through the real actions, never by setting a status. */

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

function submittedItem(?User $writer = null): ContentItem
{
    $writer ??= admin();
    $item = draftItem($writer);
    app(SubmitContentForReview::class)($item, revisionId($item), $writer);

    return $item->refresh();
}

function approvedItem(?User $writer = null, ?User $reviewer = null): ContentItem
{
    $item = submittedItem($writer);
    app(ReviewContent::class)($item, revisionId($item), ContentDecision::Approved, null, $reviewer ?? admin());

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
