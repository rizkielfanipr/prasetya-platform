<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Actions\SaveContentRevision;
use App\Domain\Content\Ai\CopyBudget;
use App\Domain\Content\Ai\CopyModels;
use App\Domain\Content\Contracts\CopyGenerator;
use App\Domain\Content\Enums\GenerationStatus;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentBrandKnowledgeAssist;
use App\Domain\Content\Models\ContentBrandKnowledgeChunk;
use App\Domain\Content\Models\ContentBrandKnowledgeVersion;
use App\Domain\Content\Models\ContentBrandProfile;
use App\Domain\Content\Models\ContentCampaign;
use App\Domain\Content\Models\ContentFact;
use App\Domain\Content\Models\ContentPillar;
use Inertia\Testing\AssertableInertia;

/* ADR-0011, phase 2: brand rules are versioned, facts are only retired, and every change is audited on the brand. */

beforeEach(fn () => contentSetUp());

/** @param array<string, mixed> $overrides */
function brandGuide(array $overrides = []): string
{
    $rules = [
        'audience' => 'Pemilik UMKM',
        'voice' => 'Ramah dan jelas',
        'visual_guidance' => '',
        'forbidden_terms' => ['termurah', ' ', 'termurah'],
        'forbidden_claims' => [],
        'allowed_ctas' => [],
        'required_hashtags' => [],
        'forbidden_hashtags' => [],
        'preferred_terms' => [],
        'approved_examples' => [],
        'rejected_examples' => [],
        'caption_min_length' => null,
        'caption_max_length' => 300,
        'hashtag_min' => null,
        'hashtag_max' => null,
        'topic_repeat_days' => null,
        ...$overrides,
    ];

    $list = static fn (string $key): string => implode("\n", $rules[$key]);
    $number = static fn (string $key): string => $rules[$key] === null ? '' : (string) $rules[$key];

    return <<<GUIDE
[AUDIENS]
{$rules['audience']}

[GAYA BAHASA]
{$rules['voice']}

[ARAHAN VISUAL]
{$rules['visual_guidance']}

[BATAS]
Caption minimal: {$number('caption_min_length')}
Caption maksimal: {$number('caption_max_length')}
Hashtag minimal: {$number('hashtag_min')}
Hashtag maksimal: {$number('hashtag_max')}
Jarak topik (hari): {$number('topic_repeat_days')}

[KATA TERLARANG]
{$list('forbidden_terms')}

[KLAIM TERLARANG]
{$list('forbidden_claims')}

[CTA DISETUJUI]
{$list('allowed_ctas')}

[HASHTAG WAJIB]
{$list('required_hashtags')}

[HASHTAG TERLARANG]
{$list('forbidden_hashtags')}

[KATA DIANJURKAN]
{$list('preferred_terms')}

[CONTOH DISETUJUI]
{$list('approved_examples')}

[CONTOH DITOLAK]
{$list('rejected_examples')}
GUIDE;
}

/** @return array{guide: string, change_note: string} */
function rulesPayload(array $overrides = []): array
{
    $changeNote = (string) ($overrides['change_note'] ?? 'Aturan awal dari pemilik');
    unset($overrides['change_note']);

    return ['guide' => brandGuide($overrides), 'change_note' => $changeNote];
}

function brandUri(string $suffix = ''): string
{
    return '/content/brands/'.webworks()->public_id.$suffix;
}

it('opens one Brand Knowledge editor and redirects the legacy campaign tab to planning', function (): void {
    $this->actingAs(admin())->get('/content/brands')->assertRedirect(brandUri());
    $this->get('/content/brands?tab=facts')->assertRedirect(brandUri());
    $this->get('/content/brands?tab=../x')->assertRedirect(brandUri());
    $this->get(brandUri())->assertInertia(fn (AssertableInertia $page) => $page->component('content/BrandKnowledgePage')->where('knowledge', null));
    $this->get(brandUri('?tab=nope'))->assertOk();
    $this->get(brandUri('?tab=campaigns'))->assertRedirect('/content/calendar?manage_campaigns=1&brand=WEBWORKS');
});

it('versions and chunks the single free-text Brand Knowledge document', function (): void {
    $uri = brandUri('/knowledge');
    $first = "Tentang brand\nKami membantu pemilik UMKM membuat website.\n\nHarga\nPaket website mulai Rp3.500.000.";
    $second = $first."\n\nFAQ\nPengerjaan dimulai setelah data lengkap diterima.";

    $this->actingAs(admin())->put($uri, ['source_text' => $first])->assertSessionHas('success', 'Brand Knowledge disimpan sebagai versi 1.');
    $this->put($uri, ['source_text' => $first])->assertSessionHas('error', 'Tidak ada Brand Knowledge yang berubah.');
    $this->put($uri, ['source_text' => $second])->assertSessionHas('success', 'Brand Knowledge disimpan sebagai versi 2.');

    $brand = webworks()->refresh();
    expect(ContentBrandKnowledgeVersion::query()->count())->toBe(2)
        ->and(ContentBrandKnowledgeChunk::query()->count())->toBe(5)
        ->and($brand->currentKnowledge?->version)->toBe(2)
        ->and($brand->currentKnowledge?->chunks)->toHaveCount(3)
        ->and(ContentBrandKnowledgeVersion::query()->where('version', 1)->value('source_text'))->toBe($first)
        ->and(AuditLog::query()->where('action', 'content.brand_knowledge_updated')->count())->toBe(2);

    expect(fn () => ContentBrandKnowledgeVersion::query()->where('version', 1)->sole()->update(['source_text' => 'ubah']))->toThrow(LogicException::class);
});

it('uses the AI interview only as an editable proposal and records the run without storing answers', function (): void {
    config()->set('content.ai.copy.provider', 'fake');
    app()->forgetInstance(CopyGenerator::class);
    app()->forgetInstance(CopyModels::class);
    app()->forgetInstance(CopyBudget::class);

    $this->actingAs(admin())->post(brandUri('/knowledge/assist'), [
        'answers' => [
            'overview' => 'Kami membantu UMKM membangun website.',
            'audience' => 'Pemilik usaha tanpa tim teknologi.',
            'offers' => '',
            'differentiators' => '',
            'voice' => 'Ramah dan mudah dipahami.',
            'visual' => '',
            'rules' => 'Jangan menjanjikan ranking pertama.',
            'operations' => '',
        ],
        'current_source_text' => 'Dokumen lama yang tetap berlaku.',
    ])->assertSessionHas('success')->assertSessionHas('brand_knowledge_assistant_draft', function (array $draft): bool {
        return str_contains($draft['text'], 'Dokumen lama yang tetap berlaku.')
            && str_contains($draft['text'], 'Kami membantu UMKM membangun website.');
    });

    $run = ContentBrandKnowledgeAssist::query()->sole();
    expect($run->status)->toBe(GenerationStatus::Succeeded)
        ->and($run->prompt_version)->toBe('brand-knowledge-v2')
        ->and(ContentBrandKnowledgeVersion::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'content.brand_knowledge_assist_requested')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'content.brand_knowledge_assist_succeeded')->count())->toBe(1)
        ->and(json_encode($run->toArray()))->not->toContain('Kami membantu UMKM');
});

it('keeps manual Brand Knowledge available when AI assistance is unavailable or unanswered', function (): void {
    $this->actingAs(admin())->post(brandUri('/knowledge/assist'), [
        'answers' => ['overview' => '', 'audience' => '', 'offers' => '', 'differentiators' => '', 'voice' => '', 'visual' => '', 'rules' => '', 'operations' => ''],
    ])->assertSessionHas('error', 'Jawab minimal satu pertanyaan sebelum meminta bantuan AI.');

    $this->post(brandUri('/knowledge/assist'), [
        'answers' => ['overview' => 'Brand kami membantu UMKM.', 'audience' => '', 'offers' => '', 'differentiators' => '', 'voice' => '', 'visual' => '', 'rules' => '', 'operations' => ''],
    ])->assertSessionHas('error', 'AI belum dikonfigurasi. Konten tetap dapat ditulis manual.');

    expect(ContentBrandKnowledgeAssist::query()->count())->toBe(0)
        ->and(ContentBrandKnowledgeVersion::query()->count())->toBe(0);
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
    $this->put(brandUri('/rules'), rulesPayload(['forbidden_terms' => array_map(static fn (int $index): string => "kata-{$index}", range(1, 51))]))->assertSessionHasErrors('guide');

    expect(ContentBrandProfile::query()->count())->toBe(1);
});

it('rejects a malformed guide without creating a rules version', function (): void {
    $this->actingAs(admin());

    $this->put(brandUri('/rules'), [
        'guide' => str_replace("[CONTOH DITOLAK]\n", '', brandGuide()),
        'change_note' => 'Heading terhapus',
    ])->assertSessionHasErrors('guide');
    $this->put(brandUri('/rules'), [
        'guide' => str_replace('Caption maksimal: 300', 'Caption maksimal: banyak', brandGuide()),
        'change_note' => 'Batas salah',
    ])->assertSessionHasErrors('guide');

    expect(ContentBrandProfile::query()->count())->toBe(0);
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
    $this->actingAs(admin())->post(brandUri('/facts'), ['category' => 'PRICE', 'statement' => 'Paket Landing Page Rp 1.500.000', 'source' => ''])->assertSessionHasErrors('source');
    $this->post(brandUri('/facts'), ['category' => 'PRICE', 'statement' => 'Paket Landing Page Rp 1.500.000', 'source' => 'Daftar harga November 2026'])->assertSessionHas('success');
    $fact = ContentFact::query()->sole();

    expect($fact->category->value)->toBe('PRICE')
        ->and(fn () => $fact->update(['statement' => 'Rp 1']))->toThrow(LogicException::class)
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

it('imports the reviewed Prasetya Webworks brand file through the audited actions, idempotently', function (): void {
    $admin = User::factory()->create(['username' => 'pemilik']);
    $file = 'resources/content-brands/prasetya-webworks.json';

    $this->artisan("content:import-brand WEBWORKS {$file} --actor=pemilik")->assertSuccessful();
    $this->artisan("content:import-brand WEBWORKS {$file} --actor=pemilik")->expectsOutputToContain('Tidak ada aturan yang berubah.')->assertSuccessful();

    $brand = webworks()->refresh();
    expect($brand->currentProfile?->version)->toBe(1)
        ->and($brand->currentProfile?->forbidden_terms)->toContain('termurah')
        ->and((int) ContentPillar::query()->where('is_active', true)->sum('target_share'))->toBe(100)
        ->and(ContentFact::query()->count())->toBe(10)
        ->and(AuditLog::query()->where('entity_type', 'content_brand')->where('actor_user_id', $admin->id)->count())->toBe(1 + 4 + 10);

    // The facts back the site's prices, so a draft quoting them passes the price rule.
    $item = draftItem();
    app(SaveContentRevision::class)($item, revisionId($item), ['caption' => 'Website siap tampil mulai Rp499.000, domain & hosting tahun pertama termasuk. Tanya via WhatsApp.', 'hashtags' => '#prasetyawebworks #websiteumkm #bisnisonline'], null, $admin);
    expect(findingCodes($item))->not->toContain('unapproved_price');
});

it('refuses an import without an active actor', function (): void {
    $this->artisan('content:import-brand WEBWORKS resources/content-brands/prasetya-webworks.json --actor=nobody')->assertFailed();
});
