<?php

declare(strict_types=1);

use App\Domain\Content\Actions\CreateContentItem;
use App\Domain\Content\Actions\MarkContentPublished;
use App\Domain\Content\Actions\MarkContentReady;
use App\Domain\Content\Actions\SaveContentRevision;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentAsset;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentPublication;
use App\Domain\Content\Support\ContentPlan;
use App\Domain\Content\Support\PostingQueue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

/* ADR-0011, phase 7: approved posts are reminded, posted by hand with a confirmed checklist, and never auto-published. */

beforeEach(function (): void {
    contentSetUp();
    $this->travelTo(CarbonImmutable::parse('2026-11-10 05:00:00', 'UTC')); // Tuesday 12:00 WIB.
});

function readyAt(string $title, string $plannedUtc): ContentItem
{
    $writer = admin();
    $item = app(CreateContentItem::class)(webworks(), new ContentPlan($title, CarbonImmutable::parse($plannedUtc)), $writer);
    $image = postImage();
    app(SaveContentRevision::class)($item, revisionId($item), completeContent(), ['path' => (string) $image->getRealPath(), 'name' => 'p.png'], $writer);
    app(MarkContentReady::class)($item, revisionId($item), $writer);

    return $item->refresh();
}

function publish(ContentItem $item, array $checklist = PostingQueue::CHECKLIST): TestResponse
{
    return test()->actingAs(admin())->post("/content/items/{$item->public_id}/publication", [
        'revision' => revisionId($item),
        'published_at' => '2026-11-10T12:00', // The approval time; never before it, never in the future.
        'checklist' => $checklist,
    ]);
}

it('groups Ready posts into overdue, today, and upcoming, and counts what is due', function (): void {
    readyAt('Kemarin', '2026-11-09T03:00:00Z');
    readyAt('Pagi ini', '2026-11-10T01:00:00Z');
    readyAt('Malam ini', '2026-11-10T14:00:00Z');
    readyAt('Minggu depan', '2026-11-17T03:00:00Z');
    readyAt('Bulan depan', '2026-12-20T03:00:00Z');
    draftItem(title: 'Masih draft');

    $this->actingAs(admin())->get('/content/to-post')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('content/ContentToPostPage')
        ->where('overdue.0.title', 'Kemarin')
        ->where('overdue.0.daysLate', 1)
        ->has('today', 2)
        ->where('upcoming.0.title', 'Minggu depan')
        ->where('later', 1)
        ->where('contentDueCount', 3));
});

it('marks rows waiting to be posted so the calendar can remind', function (): void {
    readyAt('Kemarin', '2026-11-09T03:00:00Z');

    $items = contentPage('/content/calendar?week=2026-11-09')['items'];
    expect(collect($items)->firstWhere('title', 'Kemarin')['dueState'])->toBe('overdue');
});

it('requires every checklist step and keeps it as evidence', function (): void {
    $item = readyAt('Siap', '2026-11-10T01:00:00Z');

    publish($item, ['image', 'caption'])->assertSessionHasErrors('checklist');
    publish($item, ['image', 'caption', 'alt_text', 'hack'])->assertSessionHasErrors('checklist.3');
    expect($item->refresh()->status)->toBe(ContentStatus::Ready);

    publish($item)->assertSessionHas('success');

    expect(ContentPublication::query()->sole()->checklist)->toBe(PostingQueue::CHECKLIST)
        ->and($item->refresh()->status)->toBe(ContentStatus::Published)
        ->and(contentPage("/content/items/{$item->public_id}")['item']['publication']['checklist'])->toBe(PostingQueue::CHECKLIST)
        ->and(contentPage('/content/to-post')['today'])->toBe([]);
});

it('refuses an incomplete checklist in the domain too', function (): void {
    $item = readyAt('Siap', '2026-11-10T01:00:00Z');

    expect(fn () => app(MarkContentPublished::class)($item, revisionId($item), now(), null, null, admin(), ['image']))
        ->toThrow(ContentActionRefused::class, 'Centang semua langkah checklist posting sebelum menandai sudah diposting.');
});

it('never publishes overdue posts by itself', function (): void {
    $item = readyAt('Kemarin', '2026-11-09T03:00:00Z');

    $this->travel(30)->days();
    $this->artisan('schedule:run')->assertSuccessful();
    contentPage('/content/to-post');

    expect($item->refresh()->status)->toBe(ContentStatus::Ready)->and(ContentPublication::query()->count())->toBe(0);
});

it('names the downloaded image by planned date, title, and revision', function (): void {
    $item = readyAt('Tips SEO: website UMKM!', '2026-11-12T03:00:00Z');
    $asset = ContentAsset::query()->sole();

    $this->actingAs(admin())->get("/content/assets/{$asset->public_id}?download=1")
        ->assertHeader('Content-Disposition', 'attachment; filename=2026-11-12-tips-seo-website-umkm-r1.png');
    expect($item->status)->toBe(ContentStatus::Ready);
});

it('keeps the reminder count fixed in queries however many posts wait', function (): void {
    readyAt('Satu', '2026-11-09T03:00:00Z');
    $count = function (): int {
        test()->actingAs(admin())->get('/content/to-post')->assertOk();
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        test()->get('/content/to-post')->assertOk();

        return $queries;
    };
    $few = $count();
    foreach (range(1, 3) as $n) {
        readyAt("Tambahan {$n}", '2026-11-10T01:00:00Z');
    }

    expect($count())->toBe($few);
});
