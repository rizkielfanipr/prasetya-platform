<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Content\Actions\MarkContentPublished;
use App\Domain\Content\Actions\RecordInsight;
use App\Domain\Content\Actions\UpdateContentDetails;
use App\Domain\Content\Ai\CopyPromptBuilder;
use App\Domain\Content\Models\ContentInsightSnapshot;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentPillar;
use App\Domain\Content\Support\ContentPlan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/* ADR-0011, phase 8: manual Instagram metrics, append-only, summarised; nothing changes strategy by itself. */

beforeEach(fn () => contentSetUp());

function published(string $title = 'Posting', ?ContentPillar $pillar = null, ?string $topic = null): ContentItem
{
    $item = approvedItem();
    if ($pillar !== null || $topic !== null) {
        app(UpdateContentDetails::class)($item, new ContentPlan($item->title, $item->planned_at, $pillar, null, $topic), admin());
    }
    $item->forceFill(['title' => $title])->save();
    app(MarkContentPublished::class)($item->refresh(), revisionId($item), now(), null, null, admin());

    return $item->refresh();
}

function measure(ContentItem $item, array $metrics, string $when = 'now'): ContentInsightSnapshot
{
    return app(RecordInsight::class)($item, now()->modify($when === 'now' ? '+0 minutes' : $when), $metrics, null, admin());
}

it('records metrics for a published post, append-only and audited', function (): void {
    $item = published();
    $this->travel(2)->days();

    $this->actingAs(admin())->post("/content/items/{$item->public_id}/insights", [
        'measured_at' => now()->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
        'reach' => 1200, 'likes' => 80, 'comments' => 6, 'saves' => 20, 'shares' => 4, 'note' => 'Hari ke-2',
    ])->assertSessionHas('success');

    $snapshot = ContentInsightSnapshot::query()->sole();
    expect($snapshot)->reach->toBe(1200)->views->toBeNull()->note->toBe('Hari ke-2')
        ->and($snapshot->engagementRate())->toBe(round(110 / 1200, 4))
        ->and(AuditLog::query()->where('action', 'content.insight_recorded')->sole()->new_state)->toMatchArray(['reach' => 1200, 'saves' => 20])
        ->and(fn () => $snapshot->update(['reach' => 1]))->toThrow(LogicException::class)
        ->and(contentPage("/content/items/{$item->public_id}")['insights'][0])->toMatchArray(['reach' => 1200, 'profileVisits' => null, 'engagement' => 0.0917]);
});

it('validates and refuses impossible readings', function (): void {
    $item = published();
    $this->actingAs(admin());
    $uri = "/content/items/{$item->public_id}/insights";
    $now = now()->setTimezone('Asia/Jakarta');

    $this->post($uri, ['measured_at' => $now->format('Y-m-d\TH:i')])->assertSessionHasErrors('reach');
    $this->post($uri, ['measured_at' => $now->format('Y-m-d\TH:i'), 'reach' => -1])->assertSessionHasErrors('reach');
    $this->post($uri, ['measured_at' => $now->copy()->addDay()->format('Y-m-d\TH:i'), 'reach' => 10])->assertSessionHas('error', 'Waktu pengukuran tidak boleh di masa depan.');
    $this->post($uri, ['measured_at' => $now->copy()->subDays(3)->format('Y-m-d\TH:i'), 'reach' => 10])->assertSessionHas('error', 'Waktu pengukuran tidak boleh sebelum konten diposting.');

    $draft = draftItem();
    $this->post("/content/items/{$draft->public_id}/insights", ['measured_at' => $now->format('Y-m-d\TH:i'), 'reach' => 10])
        ->assertSessionHas('error', 'Insight hanya dapat dicatat untuk konten yang sudah dipublikasikan.');
    expect(ContentInsightSnapshot::query()->count())->toBe(0);
});

it('accepts a reading in the same minute as the posting', function (): void {
    $this->travelTo(now()->startOfMinute()->addSeconds(40));
    $item = published();

    $this->actingAs(admin())->post("/content/items/{$item->public_id}/insights", [
        'measured_at' => now()->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i'), // Same minute, seconds dropped.
        'reach' => 5,
    ])->assertSessionHas('success');
});

it('summarises by pillar, campaign, and topic from each post\'s latest snapshot', function (): void {
    $edukasi = ContentPillar::query()->create(['content_brand_id' => webworks()->id, 'name' => 'Edukasi', 'target_share' => 50]);
    $promo = ContentPillar::query()->create(['content_brand_id' => webworks()->id, 'name' => 'Promo', 'target_share' => 30]);
    $a = published('Tips SEO', $edukasi, 'SEO');
    $b = published('Tips domain', $edukasi, 'domain');
    $c = published('Promo landing', $promo, 'promo');
    $this->travel(3)->days();
    measure($a, ['reach' => 1000, 'likes' => 50, 'saves' => 50]);
    measure($a, ['reach' => 2000, 'likes' => 100, 'saves' => 100]); // Later reading wins.
    measure($b, ['reach' => 1000, 'likes' => 10]);
    measure($c, ['reach' => 4000, 'likes' => 40]);

    $this->actingAs(admin())->get('/content/insights?days=30')->assertInertia(fn (AssertableInertia $page) => $page->component('content/ContentInsightsPage'));
    $summary = contentPage('/content/insights?days=30')['summary'];

    expect($summary['totals'])->toMatchArray(['posts' => 3, 'measured' => 3, 'reach' => 7000, 'saves' => 100])
        ->and(collect($summary['byPillar'])->pluck('name')->all())->toBe(['Edukasi', 'Promo'])
        ->and(collect($summary['byPillar'])->firstWhere('name', 'Edukasi'))->toMatchArray(['posts' => 2, 'avgReach' => 1500, 'avgEngagement' => 0.055])
        ->and(collect($summary['byTopic'])->pluck('name')->all())->toContain('seo', 'domain', 'promo')
        ->and($summary['top'][0])->toMatchArray(['title' => 'Tips SEO', 'reach' => 2000, 'engagement' => 0.1]);
});

it('reminds about posts published a while ago without insight', function (): void {
    $fresh = published('Baru');
    $this->travel(3)->days();
    $older = published('Sudah lama');
    $this->travel(3)->days();
    measure($older, ['reach' => 10]);

    $missing = contentPage('/content/insights?days=30')['summary']['missing'];
    expect(collect($missing)->pluck('id')->all())->toBe([$fresh->public_id]);
});

it('keeps posts outside the window and other brands out of the summary', function (): void {
    $old = published('Lama');
    $this->travel(100)->days();
    $recent = published('Baru');
    measure($recent, ['reach' => 10]);
    measure($old, ['reach' => 99]);

    expect(contentPage('/content/insights?days=90')['summary']['totals']['posts'])->toBe(1)
        ->and(contentPage('/content/insights?days=180')['summary']['totals']['posts'])->toBe(2)
        ->and(contentPage('/content/insights?days=180&brand=OTHER')['summary']['totals']['posts'])->toBe(2);
    $this->actingAs(admin())->get('/content/insights?days=7')->assertSessionHasErrors('days');
});

it('changes no rule, pillar, or prompt when insight is recorded', function (): void {
    $pillar = ContentPillar::query()->create(['content_brand_id' => webworks()->id, 'name' => 'Edukasi', 'target_share' => 50]);
    $rules = brandRules(['voice' => 'Ramah']);
    $item = published('X', $pillar);
    measure($item, ['reach' => 99999, 'likes' => 99999]);

    expect(webworks()->refresh()->current_profile_id)->toBe($rules->id)
        ->and($pillar->refresh()->target_share)->toBe(50)
        ->and(CopyPromptBuilder::VERSION)->toBe('copy-v4');
});

it('loads the insight page with a fixed number of queries', function (): void {
    $count = function (): int {
        test()->actingAs(admin())->get('/content/insights')->assertOk();
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        test()->get('/content/insights')->assertOk();

        return $queries;
    };
    measure(published('Satu'), ['reach' => 1]);
    $few = $count();
    foreach (range(1, 3) as $n) {
        measure(published("Lagi {$n}"), ['reach' => $n]);
    }

    expect($count())->toBe($few);
});
