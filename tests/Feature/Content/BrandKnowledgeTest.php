<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentBrandProfile;
use App\Domain\Content\Models\ContentCampaign;
use App\Domain\Content\Models\ContentFact;
use App\Domain\Content\Models\ContentPillar;
use Inertia\Testing\AssertableInertia;

/* ADR-0011, phase 2: brand rules are versioned, facts are only retired, and every change is audited on the brand. */

beforeEach(fn () => contentSetUp());

/** @return array<string, mixed> */
function rulesPayload(array $overrides = []): array
{
    return [
        ...array_fill_keys(ContentBrandProfile::LISTS, []),
        'audience' => 'Pemilik UMKM',
        'voice' => 'Ramah dan jelas',
        'forbidden_terms' => ['termurah', ' ', 'termurah'],
        'caption_max_length' => 300,
        'change_note' => 'Aturan awal dari pemilik',
        ...$overrides,
    ];
}

function brandUri(string $suffix = ''): string
{
    return '/content/brands/'.webworks()->public_id.$suffix;
}

it('opens Brand & Template on the first brand', function (): void {
    $this->actingAs(admin())->get('/content/brands')->assertRedirect(brandUri());
    $this->get('/content/brands?tab=facts')->assertRedirect(brandUri('?tab=facts'));
    $this->get('/content/brands?tab=../x')->assertRedirect(brandUri());
    $this->get(brandUri())->assertInertia(fn (AssertableInertia $page) => $page->component('content/ContentBrandPage')->where('rules', null)->where('tab', 'rules'));
    $this->get(brandUri('?tab=nope'))->assertSessionHasErrors('tab');
});

it('saves rules as a new version each time and keeps the old ones', function (): void {
    $this->actingAs(admin())->put(brandUri('/rules'), rulesPayload())->assertSessionHas('success', 'Aturan brand disimpan sebagai versi 1.');
    $this->put(brandUri('/rules'), rulesPayload(['caption_max_length' => 500, 'change_note' => 'Caption lebih panjang']))->assertSessionHas('success', 'Aturan brand disimpan sebagai versi 2.');

    $brand = webworks()->refresh();
    expect(ContentBrandProfile::query()->count())->toBe(2)
        ->and($brand->currentProfile?->version)->toBe(2)
        ->and($brand->currentProfile?->forbidden_terms)->toBe(['termurah'])
        ->and(ContentBrandProfile::query()->where('version', 1)->value('caption_max_length'))->toBe(300);

    $audit = AuditLog::query()->where('action', 'content.brand_rules_updated')->latest('id')->first();
    expect($audit?->entity_id)->toBe($brand->public_id)
        ->and($audit?->new_state)->toEqual(['version' => 2, 'fields' => ['caption_max_length']])
        ->and($audit?->reason)->toBe('Caption lebih panjang');
});

it('refuses a save that changes nothing, missing notes, and contradictory limits', function (): void {
    $this->actingAs(admin())->put(brandUri('/rules'), rulesPayload());

    $this->put(brandUri('/rules'), rulesPayload(['change_note' => 'Lagi']))->assertSessionHas('error', 'Tidak ada aturan yang berubah.');
    $this->put(brandUri('/rules'), rulesPayload(['change_note' => '']))->assertSessionHasErrors('change_note');
    $this->put(brandUri('/rules'), rulesPayload(['hashtag_min' => 10, 'hashtag_max' => 5, 'change_note' => 'x']))->assertSessionHas('error', 'Jumlah hashtag: batas minimal lebih besar dari batas maksimal.');
    $this->put(brandUri('/rules'), rulesPayload(['forbidden_terms' => array_fill(0, 51, 'x')]))->assertSessionHasErrors('forbidden_terms');

    expect(ContentBrandProfile::query()->count())->toBe(1);
});

it('never edits or deletes a rules version', function (): void {
    $profile = brandRules(['voice' => 'Ramah']);

    expect(fn () => $profile->update(['voice' => 'Galak']))->toThrow(LogicException::class)
        ->and(fn () => $profile->delete())->toThrow(LogicException::class);
});

it('keeps active pillar targets within 100% and names unique', function (): void {
    $this->actingAs(admin())->post(brandUri('/pillars'), ['name' => 'Edukasi', 'target_share' => 60, 'is_active' => true])->assertSessionHas('success');
    $this->post(brandUri('/pillars'), ['name' => 'Promo', 'target_share' => 50, 'is_active' => true])->assertSessionHas('error', 'Total target pilar aktif lebih dari 100%. Sisa yang tersedia: 40%.');
    $this->post(brandUri('/pillars'), ['name' => 'Promo', 'target_share' => 50, 'is_active' => false])->assertSessionHas('success');
    $this->post(brandUri('/pillars'), ['name' => 'Edukasi', 'target_share' => 10, 'is_active' => true])->assertSessionHas('error', 'Pilar "Edukasi" sudah ada.');

    $edukasi = ContentPillar::query()->where('name', 'Edukasi')->sole();
    $this->put(brandUri("/pillars/{$edukasi->public_id}"), ['name' => 'Edukasi', 'description' => 'Tips', 'target_share' => 40, 'is_active' => true])->assertSessionHas('success');

    expect($edukasi->refresh()->target_share)->toBe(40)
        ->and(AuditLog::query()->where('entity_type', 'content_brand')->pluck('action')->all())->toBe(['content.pillar_created', 'content.pillar_created', 'content.pillar_updated']);
});

it('validates campaign periods and priorities', function (): void {
    $this->actingAs(admin())->post(brandUri('/campaigns'), ['name' => 'Promo akhir tahun', 'starts_on' => '2026-12-01', 'ends_on' => '2026-11-30', 'priority' => 'HIGH', 'is_active' => true])->assertSessionHasErrors('ends_on');
    $this->post(brandUri('/campaigns'), ['name' => 'Promo akhir tahun', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-31', 'priority' => 'URGENT', 'is_active' => true])->assertSessionHasErrors('priority');
    $this->post(brandUri('/campaigns'), ['name' => 'Promo akhir tahun', 'objective' => 'Pesanan Desember', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-31', 'priority' => 'HIGH', 'is_active' => true])->assertSessionHas('success');

    $campaign = ContentCampaign::query()->sole();
    $this->put(brandUri("/campaigns/{$campaign->public_id}"), ['name' => 'Promo akhir tahun', 'starts_on' => '2026-12-01', 'ends_on' => '2027-01-05', 'priority' => 'NORMAL', 'is_active' => true])->assertSessionHas('success');

    expect($campaign->refresh()->ends_on->toDateString())->toBe('2027-01-05')
        ->and(AuditLog::query()->where('action', 'content.campaign_updated')->sole()->new_state)->toMatchArray(['ends_on' => '2027-01-05', 'priority' => 'NORMAL']);
});

it('adds facts with their source and only ever retires them', function (): void {
    $this->actingAs(admin())->post(brandUri('/facts'), ['statement' => 'Paket Landing Page Rp 1.500.000', 'source' => ''])->assertSessionHasErrors('source');
    $this->post(brandUri('/facts'), ['statement' => 'Paket Landing Page Rp 1.500.000', 'source' => 'Daftar harga November 2026'])->assertSessionHas('success');
    $fact = ContentFact::query()->sole();

    expect(fn () => $fact->update(['statement' => 'Rp 1']))->toThrow(LogicException::class)
        ->and(fn () => $fact->delete())->toThrow(LogicException::class);

    $this->post(brandUri("/facts/{$fact->public_id}/retire"), [])->assertSessionHasErrors('reason');
    $this->post(brandUri("/facts/{$fact->public_id}/retire"), ['reason' => 'Harga naik'])->assertSessionHas('success');
    $this->post(brandUri("/facts/{$fact->public_id}/retire"), ['reason' => 'Lagi'])->assertSessionHas('error', 'Fakta ini sudah tidak berlaku.');

    expect($fact->refresh()->retired_reason)->toBe('Harga naik')
        ->and(AuditLog::query()->where('entity_type', 'content_brand')->pluck('action')->all())->toBe(['content.fact_added', 'content.fact_retired']);
});

it('scopes pillars, campaigns, and facts to their brand in the URL', function (): void {
    $other = ContentBrand::factory()->create();
    $pillar = ContentPillar::query()->create(['content_brand_id' => $other->id, 'name' => 'Lain', 'target_share' => 10]);

    $this->actingAs(admin())->put(brandUri("/pillars/{$pillar->public_id}"), ['name' => 'X', 'target_share' => 10, 'is_active' => true])->assertNotFound();
    $this->get('/content/brands/'.webworks()->id)->assertNotFound();
});

it('shows brand audit on its page and keeps it out of system activity', function (): void {
    brandRules(['voice' => 'Ramah']);
    $this->actingAs(admin());

    $activity = contentPage(brandUri('?tab=activity'))['activity'];
    expect(collect($activity)->pluck('action')->all())->toBe(['content.brand_rules_updated'])
        ->and(collect(contentPage('/settings/activity')['logs']['data'])->pluck('action'))->not->toContain('content.brand_rules_updated');
});
