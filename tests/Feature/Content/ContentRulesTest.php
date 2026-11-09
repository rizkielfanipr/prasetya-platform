<?php

declare(strict_types=1);

use App\Domain\Content\Actions\AddContentFact;
use App\Domain\Content\Actions\CreateContentItem;
use App\Domain\Content\Actions\RetireContentFact;
use App\Domain\Content\Actions\SaveCampaign;
use App\Domain\Content\Actions\SaveContentRevision;
use App\Domain\Content\Enums\CampaignPriority;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentPillar;
use App\Domain\Content\Support\ContentPlan;
use Carbon\CarbonImmutable;

/* ADR-0011, phase 2: the same content always gets the same findings from the stored brand rules, with no AI. */

beforeEach(fn () => contentSetUp());

function withCaption(ContentItem $item, array $fields): ContentItem
{
    app(SaveContentRevision::class)($item, revisionId($item), $fields, null, admin());

    return $item->refresh();
}

it('says the rules are missing until a brand has them', function (): void {
    expect(findingCodes(draftItem()))->toBe(['no_profile']);
});

it('finds forbidden words and claims in public text only, as whole words', function (): void {
    brandRules(['forbidden_terms' => ['murah'], 'forbidden_claims' => ['dijamin ranking 1']]);
    $item = draftItem();

    expect(findingCodes(withCaption($item, ['caption' => 'Website Murah!', 'brief' => 'murah sekali'])))->toBe(['forbidden_term'])
        ->and(findingCodes(withCaption($item, ['caption' => 'Dimurahkan? Tidak.'])))->toBe([])
        ->and(findingCodes(withCaption($item, ['caption' => 'Website dijamin  ranking 1 di Google'])))->toBe(['forbidden_claim'])
        ->and(findingCodes(withCaption($item, ['caption' => 'Rapi.', 'alt_text' => 'Kartu murah'])))->toBe(['forbidden_term']);
});

it('checks caption length and hashtags against the brand limits', function (): void {
    brandRules(['caption_min_length' => 20, 'caption_max_length' => 40, 'hashtag_min' => 2, 'hashtag_max' => 3, 'required_hashtags' => ['#PrasetyaWebworks'], 'forbidden_hashtags' => ['spam']]);
    $item = draftItem();

    expect(findingCodes(withCaption($item, ['caption' => 'Pendek', 'hashtags' => '#prasetyawebworks #umkm'])))->toBe(['caption_too_short'])
        ->and(findingCodes(withCaption($item, ['caption' => str_repeat('a', 41), 'hashtags' => '#prasetyawebworks #umkm'])))->toBe(['caption_too_long'])
        ->and(findingCodes(withCaption($item, ['caption' => 'Website rapi untuk bisnis Anda.', 'hashtags' => '#a #b #c #spam'])))->toBe(['too_many_hashtags', 'forbidden_hashtag', 'missing_hashtags']);
});

it('asks for an approved call to action when the brand has some', function (): void {
    brandRules(['allowed_ctas' => ['Hubungi kami lewat DM']]);
    $item = draftItem();

    expect(findingCodes(withCaption($item, ['caption' => 'Website rapi.'])))->toBe(['no_cta'])
        ->and(findingCodes(withCaption($item, ['caption' => 'Website rapi. Hubungi kami lewat DM.'])))->toBe([]);
});

it('accepts prices and percentages only from approved, current facts', function (): void {
    brandRules(['voice' => 'Ramah']);
    $item = draftItem();
    $fact = app(AddContentFact::class)(webworks(), null, 'Landing page mulai Rp 1.500.000', 'Daftar harga', null, admin());

    expect(findingCodes(withCaption($item, ['caption' => 'Landing page mulai Rp1.500.000.'])))->toBe([])
        ->and(findingCodes(withCaption($item, ['caption' => 'Landing page mulai Rp 999.000'])))->toBe(['unapproved_price'])
        ->and(findingCodes(withCaption($item, ['caption' => 'Diskon 20% untuk Anda'])))->toBe(['unapproved_number']);

    app(RetireContentFact::class)(webworks(), $fact, 'Harga berubah', admin());
    expect(findingCodes(withCaption($item, ['caption' => 'Landing page mulai Rp 1.500.000'])))->toBe(['unapproved_price']);
});

it('does not let an expired fact or another campaign\'s fact back a claim', function (): void {
    brandRules(['voice' => 'Ramah']);
    $campaign = app(SaveCampaign::class)(webworks(), null, 'Desember', null, CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2026-12-31'), CampaignPriority::High, true, admin());
    app(AddContentFact::class)(webworks(), $campaign, 'Diskon 20% selama Desember', 'Persetujuan pemilik', null, admin());
    app(AddContentFact::class)(webworks(), null, 'Gratis domain senilai Rp 150.000', 'Daftar harga', CarbonImmutable::parse('2026-11-01'), admin());

    $item = withCaption(draftItem(), ['caption' => 'Diskon 20% dan gratis domain Rp 150.000']);
    expect(findingCodes($item))->toBe(['unapproved_number', 'unapproved_price']); // In the order they appear.

    $inCampaign = app(CreateContentItem::class)(webworks(), new ContentPlan('Promo', CarbonImmutable::parse('2026-12-05T03:00:00Z'), campaign: $campaign), admin());
    expect(findingCodes(withCaption($inCampaign, ['caption' => 'Diskon 20% bulan ini'])))->toBe([]);
});

it('warns about campaign periods, inactive plans, and repeated topics', function (): void {
    brandRules(['topic_repeat_days' => 14]);
    $campaign = app(SaveCampaign::class)(webworks(), null, 'Desember', null, CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2026-12-31'), CampaignPriority::Normal, true, admin());
    $pillar = ContentPillar::query()->create(['content_brand_id' => webworks()->id, 'name' => 'Edukasi', 'target_share' => 50]);

    $first = app(CreateContentItem::class)(webworks(), new ContentPlan('SEO dasar', CarbonImmutable::parse('2026-11-10T03:00:00Z'), topic: 'SEO'), admin());
    $second = app(CreateContentItem::class)(webworks(), new ContentPlan('SEO lanjutan', CarbonImmutable::parse('2026-11-20T03:00:00Z'), $pillar, $campaign, ' seo '), admin());
    $pillar->update(['is_active' => false]);

    expect(findingCodes($second))->toBe(['topic_repeated', 'inactive_pillar', 'outside_campaign'])
        ->and(findingCodes($first))->toBe(['topic_repeated']);
});

it('blocks Ready on errors, allows it with warnings, and records the rules version', function (): void {
    brandRules(['forbidden_terms' => ['murah'], 'allowed_ctas' => ['DM kami']]);
    $writer = admin();
    $item = withCaption(draftItem($writer), ['caption' => 'Website murah']);

    $this->actingAs($writer)->post("/content/items/{$item->public_id}/ready", ['revision' => revisionId($item)])
        ->assertSessionHas('error', 'Perbaiki 1 pelanggaran aturan brand sebelum menandai siap posting.');
    expect(contentPage("/content/items/{$item->public_id}", $writer))
        ->toMatchArray(['brandRulesVersion' => 1])
        ->and(collect(contentPage("/content/items/{$item->public_id}", $writer)['findings'])->pluck('severity')->all())->toBe(['ERROR', 'WARNING']);

    withCaption($item, ['caption' => 'Website rapi untuk usaha Anda']); // Still no CTA: a warning only.
    $this->post("/content/items/{$item->public_id}/ready", ['revision' => revisionId($item)])->assertSessionHas('success');

    expect($item->refresh()->status)->toBe(ContentStatus::Ready)
        ->and($item->activeRevision?->brand_profile_id)->toBe(webworks()->refresh()->current_profile_id);

    // Rules changing later never rewrite which version the Ready revision met.
    brandRules(['forbidden_terms' => ['usaha']], 'Lebih ketat');
    expect($item->activeRevision?->refresh()->brandProfile?->version)->toBe(1);
});

it('saves an edit of Ready content that breaks a brand rule as a Draft, which cannot be Ready until fixed', function (): void {
    brandRules(['forbidden_terms' => ['murah']]);
    $item = readyItem();

    $this->actingAs(admin())->put("/content/items/{$item->public_id}", ['revision' => revisionId($item), 'caption' => 'Sekarang murah'])->assertSessionHas('success');
    expect($item->refresh()->status)->toBe(ContentStatus::Draft);

    $this->post("/content/items/{$item->public_id}/ready", ['revision' => revisionId($item)])
        ->assertSessionHas('error', 'Perbaiki 1 pelanggaran aturan brand sebelum menandai siap posting.');
    expect($item->refresh()->status)->toBe(ContentStatus::Draft);
});

it('plans an item with a pillar, campaign, and topic of its own brand only', function (): void {
    $pillar = ContentPillar::query()->create(['content_brand_id' => webworks()->id, 'name' => 'Edukasi', 'target_share' => 50]);
    $foreign = ContentPillar::query()->create(['content_brand_id' => ContentBrand::factory()->create()->id, 'name' => 'Lain', 'target_share' => 10]);
    $item = draftItem();
    $uri = "/content/items/{$item->public_id}/details";

    $this->actingAs(admin())->put($uri, ['title' => 'Judul', 'planned_at' => '2026-11-12T10:00', 'pillar' => $foreign->public_id])->assertSessionHas('error', 'Pilar ini milik brand lain.');
    $this->put($uri, ['title' => 'Judul', 'planned_at' => '2026-11-12T10:00', 'pillar' => $pillar->public_id, 'topic' => 'SEO'])->assertSessionHas('success');

    expect($item->refresh()->content_pillar_id)->toBe($pillar->id)->and($item->topic)->toBe('SEO');

    $pillar->update(['is_active' => false]);
    $this->put($uri, ['title' => 'Judul baru', 'planned_at' => '2026-11-12T10:00', 'pillar' => $pillar->public_id, 'topic' => 'SEO'])->assertSessionHas('success');
    expect($item->refresh()->title)->toBe('Judul baru');
});

it('shows the week\'s pillar composition against targets and filters by pillar', function (): void {
    $edukasi = ContentPillar::query()->create(['content_brand_id' => webworks()->id, 'name' => 'Edukasi', 'target_share' => 60]);
    ContentPillar::query()->create(['content_brand_id' => webworks()->id, 'name' => 'Promo', 'target_share' => 40]);
    foreach (['2026-11-10', '2026-11-11'] as $day) {
        app(CreateContentItem::class)(webworks(), new ContentPlan("Edukasi {$day}", CarbonImmutable::parse("{$day}T03:00:00Z"), $edukasi), admin());
    }
    app(CreateContentItem::class)(webworks(), new ContentPlan('Tanpa pilar', CarbonImmutable::parse('2026-11-12T03:00:00Z')), admin());

    $props = contentPage('/content/calendar?week=2026-11-09');
    $composition = $props['composition'][0];

    expect($composition['total'])->toBe(3)
        ->and($composition['unassigned'])->toBe(1)
        ->and(collect($composition['pillars'])->pluck('count', 'name')->all())->toBe(['Edukasi' => 2, 'Promo' => 0])
        ->and(contentPage("/content/calendar?week=2026-11-09&pillar={$edukasi->public_id}")['items'])->toHaveCount(2)
        ->and(contentPage("/content/items?pillar={$edukasi->public_id}")['items']['meta']['total'])->toBe(2);
});
