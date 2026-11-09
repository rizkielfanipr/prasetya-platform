<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Content\Actions\CreateContentItem;
use App\Domain\Content\Actions\MarkContentReady;
use App\Domain\Content\Actions\SaveContentRevision;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Support\ContentPlan;
use App\Domain\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/* ADR-0011: Kalender, Konten, and the item workspace. */

beforeEach(fn () => contentSetUp());

function planned(string $title, string $utc, ?ContentBrand $brand = null): ContentItem
{
    return app(CreateContentItem::class)($brand ?? webworks(), new ContentPlan($title, CarbonImmutable::parse($utc)), admin());
}

it('shows one week, Monday to Sunday in business time', function (): void {
    planned('Senin pagi WIB', '2026-11-08T17:30:00Z'); // Monday 9 Nov, 00:30 WIB.
    planned('Minggu malam WIB', '2026-11-15T16:59:00Z'); // Sunday 15 Nov, 23:59 WIB.
    planned('Minggu sebelumnya', '2026-11-08T16:59:00Z'); // Sunday 8 Nov, 23:59 WIB.
    planned('Minggu berikutnya', '2026-11-15T17:00:00Z'); // Monday 16 Nov, 00:00 WIB.

    $props = contentPage('/content/calendar?week=2026-11-12');

    expect($props['week'])->toMatchArray(['start' => '2026-11-09', 'end' => '2026-11-15', 'previous' => '2026-11-02', 'next' => '2026-11-16'])
        ->and($props['week']['days'])->toHaveCount(7)
        ->and(collect($props['items'])->pluck('title')->all())->toBe(['Senin pagi WIB', 'Minggu malam WIB'])
        ->and(collect($props['items'])->pluck('plannedDay')->all())->toBe(['2026-11-09', '2026-11-15']);
});

it('renders the calendar page and filters it by status and brand', function (): void {
    $other = ContentBrand::factory()->create(['code' => 'SATAMU', 'name' => 'Satamu']);
    planned('Webworks', now()->toIso8601String());
    planned('Satamu', now()->toIso8601String(), $other);

    $this->actingAs(admin())->get('/content/calendar')->assertInertia(fn (AssertableInertia $page) => $page->component('content/ContentCalendarPage')->has('items', 2));

    expect(contentPage('/content/calendar?brand=SATAMU')['items'])->toHaveCount(1)
        ->and(contentPage('/content/calendar?status=READY')['items'])->toHaveCount(0)
        ->and(contentPage('/content/calendar?status=DRAFT')['items'])->toHaveCount(2)
        // A bookmark on a retired status reads as the status it became.
        ->and(contentPage('/content/calendar?status=READY_FOR_REVIEW')['filters']['status'])->toBe('DRAFT')
        ->and(contentPage('/content/calendar?status=APPROVED')['filters']['status'])->toBe('READY')
        ->and(collect(contentPage('/content/calendar')['brands'])->pluck('code')->all())->toBe(['WEBWORKS', 'SATAMU']);
});

it('validates calendar and list filters', function (string $uri, string $field): void {
    $this->actingAs(admin())->get($uri)->assertSessionHasErrors($field);
})->with([
    ['/content/calendar?week=next-week', 'week'],
    ['/content/calendar?status=LIVE', 'status'],
    ['/content/calendar?view=list&sort=id', 'sort'],
    ['/content/calendar?view=list&direction=sideways', 'direction'],
    ['/content/calendar?view=table', 'view'],
]);

it('lists, searches, sorts, and pages items in the Kalender list view', function (): void {
    foreach (range(1, 21) as $n) {
        planned("Posting {$n}", now()->addDays($n)->toIso8601String());
    }
    planned('Cerita klien toko roti', now()->subDay()->toIso8601String());
    $list = static fn (string $query = ''): array => contentPage("/content/calendar?view=list{$query}")['list'];

    $this->actingAs(admin())->get('/content/calendar?view=list')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('content/ContentCalendarPage')
        ->where('filters.view', 'list')
        ->missing('week')
        ->missing('composition'));

    expect($list()['meta']['total'])->toBe(22)
        ->and($list('&page=2')['data'])->toHaveCount(2)
        ->and($list()['data'][0]['title'])->toBe('Posting 21')
        ->and($list('&sort=planned_at&direction=asc')['data'][0]['title'])->toBe('Cerita klien toko roti')
        ->and($list('&search=roti')['meta']['total'])->toBe(1)
        ->and($list('&search=%25')['meta']['total'])->toBe(0);
});

it('hides archived items unless the archive filter asks for them', function (): void {
    planned('Masih jalan', now()->toIso8601String());
    $archived = draftItem(title: 'Sudah dibatalkan');
    $this->actingAs(admin())->post("/content/items/{$archived->public_id}/archive", ['revision' => revisionId($archived)])->assertSessionHas('success');
    $titles = static fn (string $uri): array => collect(contentPage($uri)['list']['data'])->pluck('title')->all();

    expect($titles('/content/calendar?view=list'))->toBe(['Masih jalan'])
        ->and($titles('/content/calendar?view=list&status=ARCHIVED'))->toBe(['Sudah dibatalkan'])
        ->and(collect(contentPage('/content/calendar')['items'])->pluck('title')->all())->not->toContain('Sudah dibatalkan');
});

it('redirects the retired Konten and Siap posting pages to the Kalender with the same filters, without a loop', function (): void {
    $this->actingAs(admin());

    $this->get('/content/items?search=roti&status=DRAFT&sort=updated_at&direction=asc&page=2&evil=1')
        ->assertRedirect('/content/calendar?view=list&status=DRAFT&search=roti&sort=updated_at&direction=asc&page=2');
    $this->get('/content/items')->assertRedirect('/content/calendar?view=list');
    $this->get('/content/to-post')->assertRedirect('/content/calendar?view=list&status=READY&sort=planned_at&direction=asc');
    $this->get('/content/calendar?view=list&status=READY&sort=planned_at&direction=asc')->assertOk();
    // A legacy status in an old bookmark still lands on its new status.
    $this->followingRedirects()->get('/content/items?status=APPROVED')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('content/ContentCalendarPage')->where('filters.status', 'READY'));
});

it('shows an item with its revisions, decisions, and its own audit', function (): void {
    $item = readyItem();
    $other = draftItem();

    $this->actingAs(admin())->get("/content/items/{$item->public_id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->component('content/ContentDetailPage')
        ->where('item.id', $item->public_id)
        ->where('item.status', 'READY')
        ->where('item.readyRevisionId', fn ($id) => $id !== null)
        ->where('revision.number', 1)
        ->has('revisions', 1)
        ->has('revision.asset.url')
        ->missing('revision.asset.path'));

    $activity = contentPage("/content/items/{$item->public_id}")['activity'];
    expect(collect($activity)->pluck('entityId')->unique()->all())->toBe([$item->public_id])
        ->and(collect($activity)->pluck('action')->all())->toBe(['content.marked_ready', 'content.revised', 'content.created'])
        ->and($other->public_id)->not->toBe($item->public_id);
});

it('keeps content audit out of system activity', function (): void {
    draftItem();
    $this->actingAs(admin());

    $system = collect(contentPage('/settings/activity')['logs']['data'])->pluck('action');
    $all = collect(contentPage('/settings/activity?scope=all')['logs']['data'])->pluck('action');

    expect($system)->not->toContain('content.created')->not->toContain('content.revised')
        ->and($all)->toContain('content.created');
});

it('loads an item with a fixed number of queries, however many revisions', function (): void {
    $writer = admin();
    $grow = function (ContentItem $item, int $times) use ($writer): void {
        foreach (range(1, $times) as $n) {
            app(MarkContentReady::class)($item, revisionId($item), $writer);
            app(SaveContentRevision::class)($item, revisionId($item), ['caption' => "Caption {$n}"], null, $writer);
        }
    };
    $small = draftItem($writer);
    $large = draftItem($writer);
    $grow($small, 1);
    $grow($large, 5);

    $count = function (string $uri): int {
        test()->get($uri)->assertOk();
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        test()->get($uri)->assertOk();

        return $queries;
    };
    $this->actingAs($writer);

    expect($count("/content/items/{$large->public_id}"))->toBe($count("/content/items/{$small->public_id}"));
});

it('never puts customer, order, or payment data in content screens', function (): void {
    $order = Order::factory()->create(['customer_name' => 'Pelanggan Rahasia', 'customer_email' => 'rahasia@example.test']);
    $item = draftItem();

    foreach (['/content/calendar', '/content/calendar?view=list', "/content/items/{$item->public_id}"] as $uri) {
        expect($this->actingAs(admin())->get($uri)->getContent())
            ->not->toContain('Pelanggan Rahasia')->not->toContain('rahasia@example.test')->not->toContain($order->invoice_number);
    }
});

it('writes content audit only through the content actions', function (): void {
    draftItem();

    expect(AuditLog::query()->where('entity_type', 'content_item')->pluck('action')->all())->toBe(['content.created', 'content.revised']);
});
