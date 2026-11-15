<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Actions\SaveBrandKnowledge;
use App\Domain\Content\Actions\SaveCampaign;
use App\Domain\Content\Actions\SaveContentRevision;
use App\Domain\Content\Ai\CopyBudget;
use App\Domain\Content\Ai\CopyModels;
use App\Domain\Content\Contracts\CopyGenerator;
use App\Domain\Content\Enums\CampaignPriority;
use App\Domain\Content\Enums\GenerationStatus;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentBrandKnowledgeAssist;
use App\Domain\Content\Models\ContentBrandKnowledgeChunk;
use App\Domain\Content\Models\ContentBrandKnowledgeVersion;
use App\Domain\Content\Models\ContentBrandProfile;
use App\Domain\Content\Models\ContentCampaign;
use App\Domain\Content\Models\ContentFact;
use App\Domain\Content\Models\ContentPillar;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

/* ADR-0011 and ADR-0017: one Brand Knowledge document; its Aturan Posting section versions the rules, and every change is audited. */

beforeEach(fn () => contentSetUp());

/** A Brand Knowledge document whose `## Aturan Posting` section holds the given rules (ADR-0017). */
function knowledgeWithRules(string $limits = "Caption minimal: 80\nCaption maksimal: 700\nHashtag minimal: 3\nHashtag maksimal: 10\nJarak topik (hari): 21", string $terms = 'termurah'): string
{
    return <<<DOC
    # Prasetya Webworks
    Kami membantu UMKM membuat website. Paket mulai Rp499.000.

    ## Aturan Posting
    Aturan ini dibaca otomatis saat disimpan.

    [BATAS]
    {$limits}

    [KATA TERLARANG]
    {$terms}

    [KLAIM TERLARANG]
    garansi uang kembali

    [CTA DISETUJUI]
    Tanya via WhatsApp

    [HASHTAG WAJIB]
    #prasetyawebworks

    ## FAQ
    Pengerjaan dimulai setelah data lengkap.
    DOC;
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

it('reads the Aturan Posting section into a new rules version only when the rules change', function (): void {
    $uri = brandUri('/knowledge');
    $this->actingAs(admin())->put($uri, ['source_text' => knowledgeWithRules()])->assertSessionHas('success', 'Brand Knowledge disimpan sebagai versi 1.');

    $profile = webworks()->refresh()->currentProfile;
    expect($profile?->version)->toBe(1)
        ->and($profile?->forbidden_terms)->toBe(['termurah'])
        ->and($profile?->forbidden_claims)->toBe(['garansi uang kembali'])
        ->and($profile?->allowed_ctas)->toBe(['Tanya via WhatsApp'])
        ->and($profile?->required_hashtags)->toBe(['#prasetyawebworks'])
        ->and($profile?->caption_max_length)->toBe(700)
        ->and($profile?->change_note)->toBe('Dari Brand Knowledge versi 1');

    // Prose outside the section changes: a new knowledge version, the same rules version.
    $this->put($uri, ['source_text' => knowledgeWithRules().' Ditambah.'])->assertSessionHas('success', 'Brand Knowledge disimpan sebagai versi 2.');
    expect(ContentBrandProfile::query()->count())->toBe(1);

    $this->put($uri, ['source_text' => knowledgeWithRules(terms: "termurah\nmurahan")])->assertSessionHas('success');
    $brand = webworks()->refresh();
    expect($brand->currentProfile?->version)->toBe(2)
        ->and($brand->currentProfile?->forbidden_terms)->toBe(['termurah', 'murahan'])
        ->and(AuditLog::query()->where('action', 'content.brand_rules_updated')->latest('id')->first()?->new_state)->toEqual(['version' => 2, 'fields' => ['forbidden_terms']]);
});

it('keeps the rules in force when the document has no Aturan Posting section', function (): void {
    brandRules(['forbidden_terms' => ['murah']]);

    $this->actingAs(admin())->put(brandUri('/knowledge'), ['source_text' => "# Brand\nTanpa aturan di dokumen."])->assertSessionHas('success');

    expect(webworks()->refresh()->currentProfile?->forbidden_terms)->toBe(['murah'])
        ->and(ContentBrandProfile::query()->count())->toBe(1)
        ->and(contentPage(brandUri())['rules'])->toMatchArray(['version' => 1, 'inDocument' => false]);
});

it('carries the fields the section does not hold into the new rules version', function (): void {
    brandRules(['voice' => 'Ramah dan jelas', 'audience' => 'Pemilik UMKM']);

    $this->actingAs(admin())->put(brandUri('/knowledge'), ['source_text' => knowledgeWithRules()])->assertSessionHas('success');

    $profile = webworks()->refresh()->currentProfile;
    expect($profile?->version)->toBe(2)->and($profile?->voice)->toBe('Ramah dan jelas')->and($profile?->audience)->toBe('Pemilik UMKM');
});

it('refuses a malformed or contradictory section and saves nothing', function (string $limits, string $message): void {
    $this->actingAs(admin())->put(brandUri('/knowledge'), ['source_text' => knowledgeWithRules($limits)])->assertSessionHas('error', $message);

    expect(ContentBrandKnowledgeVersion::query()->count())->toBe(0)->and(ContentBrandProfile::query()->count())->toBe(0);
})->with([
    'unknown limit' => ['Caption maksimal: banyak', 'Aturan Posting: format [BATAS] tidak valid. Tulis misalnya “Caption maksimal: 700”, dengan label “Caption minimal”, “Caption maksimal”, “Hashtag minimal”, “Hashtag maksimal”, “Jarak topik (hari)”.'],
    'out of range' => ['Hashtag maksimal: 99', 'Aturan Posting: batas “Hashtag maksimal” harus antara 0 dan 30.'],
    'contradictory' => ["Hashtag minimal: 10\nHashtag maksimal: 5", 'Jumlah hashtag: batas minimal lebih besar dari batas maksimal.'],
]);

it('refuses an unknown block in the section', function (): void {
    $this->actingAs(admin())->put(brandUri('/knowledge'), ['source_text' => str_replace('[CTA DISETUJUI]', '[CTA]', knowledgeWithRules())])
        ->assertSessionHas('error', fn (string $error): bool => str_starts_with($error, 'Aturan Posting: bagian [CTA] tidak dikenali.'));
});

it('offers the rules in force as a section that reads back to the same rules', function (): void {
    $current = brandRules(['forbidden_terms' => ['termurah'], 'forbidden_claims' => ['dijamin laris'], 'caption_max_length' => 700, 'required_hashtags' => ['#prasetyawebworks']]);
    $this->actingAs(admin());

    $rules = contentPage(brandUri())['rules'];
    expect($rules['inDocument'])->toBeFalse()->and($rules['section'])->toStartWith('## Aturan Posting');

    $this->put(brandUri('/knowledge'), ['source_text' => "# Brand\nIsi.\n\n".$rules['section']])->assertSessionHas('success');
    // Moving the rules into the document changes nothing about them.
    expect(webworks()->refresh()->current_profile_id)->toBe($current->id)
        ->and(contentPage(brandUri())['rules'])->toMatchArray(['version' => 1, 'inDocument' => true]);
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

it('creates a campaign with only name, period, and objective, and keeps legacy fields when they are not sent', function (): void {
    $this->actingAs(admin())->post(brandUri('/campaigns'), ['name' => 'Ramadan', 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-30', 'objective' => 'Pesanan lebaran'])
        ->assertSessionHas('success');
    $campaign = ContentCampaign::query()->sole();
    expect($campaign->priority)->toBe(CampaignPriority::Normal)->and($campaign->is_active)->toBeTrue();

    // A legacy campaign switched off by hand with a high priority keeps both when edited through the minimal form.
    $campaign->update(['priority' => CampaignPriority::High, 'is_active' => false]);
    $this->put(brandUri("/campaigns/{$campaign->public_id}"), ['name' => 'Ramadan 2026', 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-30', 'objective' => 'Pesanan lebaran'])
        ->assertSessionHas('success');
    expect($campaign->refresh())->name->toBe('Ramadan 2026')->priority->toBe(CampaignPriority::High)->is_active->toBeFalse();
    $this->put(brandUri("/campaigns/{$campaign->public_id}"), ['name' => 'Ramadan 2026', 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-30', 'is_active' => true])
        ->assertSessionHas('success');
    expect($campaign->refresh()->is_active)->toBeTrue();
});

it('derives whether a campaign runs from its period in WIB, at the day boundary', function (): void {
    $save = static fn (string $name, string $from, string $to) => app(SaveCampaign::class)(webworks(), null, $name, null, CarbonImmutable::parse($from), CarbonImmutable::parse($to), CampaignPriority::Normal, true, admin());
    $save('Lalu', '2026-11-01', '2026-11-08');
    $save('Kini', '2026-11-09', '2026-11-09');
    $save('Nanti', '2026-11-10', '2026-11-20');

    // 2026-11-08 17:00 UTC is already 9 November 00:00 WIB.
    $this->travelTo(CarbonImmutable::parse('2026-11-08T17:00:00Z'));
    $periods = collect(contentPage('/content/calendar')['brands'][0]['campaigns'])->pluck('period', 'name')->all();

    expect($periods)->toEqual(['Lalu' => 'ended', 'Kini' => 'running', 'Nanti' => 'upcoming']);
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

    // Imported facts no longer back a price (ADR-0017): Brand Knowledge does.
    $item = draftItem();
    app(SaveContentRevision::class)($item, revisionId($item), ['caption' => 'Website siap tampil mulai Rp499.000, domain & hosting tahun pertama termasuk. Tanya via WhatsApp.', 'hashtags' => '#prasetyawebworks #websiteumkm #bisnisonline'], null, $admin);
    expect(findingCodes($item))->toContain('unapproved_price');
    app(SaveBrandKnowledge::class)(webworks(), "## Harga\nWebsite mulai Rp499.000.", $admin);
    expect(findingCodes($item->refresh()))->not->toContain('unapproved_price');
});

it('refuses an import without an active actor', function (): void {
    $this->artisan('content:import-brand WEBWORKS resources/content-brands/prasetya-webworks.json --actor=nobody')->assertFailed();
});

it('has no separate rules or facts editor any more (ADR-0017)', function (): void {
    $this->actingAs(admin())->put(brandUri('/rules'), ['guide' => 'x', 'change_note' => 'x'])->assertNotFound();
    $this->post(brandUri('/facts'), ['category' => 'PRICE', 'statement' => 'Rp1', 'source' => 'x'])->assertNotFound();
    $this->post('/content/items/'.draftItem()->public_id.'/research', ['query' => 'website umkm'])->assertNotFound();
});

it('moves Pengaturan AI to Pengaturan → Model AI', function (): void {
    $this->actingAs(admin())->get('/content/settings')->assertRedirect('/settings/ai');
    $this->get('/settings/ai')->assertInertia(fn (AssertableInertia $page) => $page->component('settings/AiModelPage'));
});
