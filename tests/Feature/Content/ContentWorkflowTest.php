<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Enums\ContentDecision;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentApproval;
use App\Domain\Content\Models\ContentCampaign;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentPillar;
use App\Domain\Content\Models\ContentPublication;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Support\IdeaPlan;
use App\Domain\Content\Support\PostingQueue;
use App\Support\BusinessTime;
use Carbon\CarbonInterface;

/*
 * ADR-0012: the creator-owned lifecycle (Draft, Ready, Published, Archived). One signed-in person drafts, marks Ready,
 * and records the post; there is no approval step. Revisions stay immutable once frozen, and only a person publishes.
 */

beforeEach(fn () => contentSetUp());

function postedAtInput(CarbonInterface $at): string
{
    return $at->setTimezone(BusinessTime::zone())->format(BusinessTime::LOCAL_FORMAT);
}

/** @return list<string> */
function contentAudit(ContentItem $item): array
{
    return AuditLog::query()->where('entity_type', 'content_item')->where('entity_id', $item->public_id)->orderBy('id')->pluck('action')->all();
}

it('creates a draft with an empty first revision, in business time', function (): void {
    $writer = admin();

    $this->actingAs($writer)->post('/content/items', ['brand' => 'WEBWORKS', 'title' => 'Posting pertama', 'planned_at' => '2026-11-10T10:00'])
        ->assertRedirect()->assertSessionHas('success');

    $item = ContentItem::query()->sole();
    expect($item->status)->toBe(ContentStatus::Draft)
        ->and($item->planned_at->toIso8601ZuluString())->toBe('2026-11-10T03:00:00Z')
        ->and($item->activeRevision?->number)->toBe(1)
        ->and($item->activeRevision?->isSubmitted())->toBeFalse()
        ->and(contentAudit($item))->toBe(['content.created']);
});

it('creates a draft from an idea: the first line becomes the title and the whole idea the brief', function (): void {
    $idea = "3 tanda website Anda perlu diperbarui\nUntuk pemilik toko online yang websitenya lambat.";

    $this->actingAs(admin())->post('/content/items', ['brand' => 'WEBWORKS', 'idea' => "  {$idea}  ", 'planned_at' => '2026-11-10T10:00'])
        ->assertRedirect()->assertSessionHas('success');

    $item = ContentItem::query()->sole();
    expect($item->status)->toBe(ContentStatus::Draft)
        ->and($item->title)->toBe('3 tanda website Anda perlu diperbarui')
        ->and($item->activeRevision?->brief)->toBe($idea)
        ->and($item->activeRevision?->caption)->toBeNull()
        ->and($item->content_pillar_id)->toBeNull()
        ->and(AuditLog::query()->where('action', 'content.created')->sole()->new_state)->toMatchArray(['from_idea' => true]);
});

it('shortens a long idea into a title at a word boundary', function (): void {
    expect(IdeaPlan::title(str_repeat('website rapi ', 12)))->toBe('website rapi website rapi website rapi website rapi website rapi website rapi…')
        ->and(mb_strlen(IdeaPlan::title(str_repeat('a', 200))))->toBe(IdeaPlan::TITLE_LENGTH)
        ->and(IdeaPlan::title("  Satu\tbaris   saja \n kedua"))->toBe('Satu baris saja');
});

it('picks a pillar only when exactly one active pillar is named in the idea', function (): void {
    $edukasi = ContentPillar::query()->create(['content_brand_id' => webworks()->id, 'name' => 'Edukasi', 'target_share' => 50]);
    ContentPillar::query()->create(['content_brand_id' => webworks()->id, 'name' => 'Promo', 'target_share' => 30]);
    ContentPillar::query()->create(['content_brand_id' => webworks()->id, 'name' => 'Portofolio', 'target_share' => 20, 'is_active' => false]);

    expect(IdeaPlan::pillar(webworks(), 'Konten edukasi tentang SSL')?->id)->toBe($edukasi->id)
        ->and(IdeaPlan::pillar(webworks(), 'Edukasi dan promo akhir tahun'))->toBeNull()
        ->and(IdeaPlan::pillar(webworks(), 'Portofolio toko roti'))->toBeNull()
        ->and(IdeaPlan::pillar(webworks(), 'Promosi website'))->toBeNull();

    $this->actingAs(admin())->post('/content/items', ['brand' => 'WEBWORKS', 'idea' => 'Edukasi: apa itu SSL', 'planned_at' => '2026-11-10T10:00']);
    expect(ContentItem::query()->sole()->content_pillar_id)->toBe($edukasi->id);
});

it('takes an optional campaign of the brand when creating from an idea', function (): void {
    $campaign = ContentCampaign::query()->create(['content_brand_id' => webworks()->id, 'name' => 'Akhir tahun', 'starts_on' => '2026-11-01', 'ends_on' => '2026-12-31', 'priority' => 'NORMAL']);

    $this->actingAs(admin())->post('/content/items', ['brand' => 'WEBWORKS', 'idea' => 'Promo akhir tahun', 'planned_at' => '2026-11-10T10:00', 'campaign' => $campaign->public_id])
        ->assertSessionHas('success');

    expect(ContentItem::query()->sole()->content_campaign_id)->toBe($campaign->id);

    // A campaign that does not run on the planned day (WIB) is not offered, and refused if sent anyway.
    $this->post('/content/items', ['brand' => 'WEBWORKS', 'idea' => 'Terlalu awal', 'planned_at' => '2026-10-31T23:59', 'campaign' => $campaign->public_id])
        ->assertSessionHas('error', 'Campaign "Akhir tahun" tidak berjalan pada tanggal ini.');
    expect(ContentItem::query()->count())->toBe(1);
});

it('validates a new item', function (array $payload, string $field): void {
    $this->actingAs(admin())->post('/content/items', [...['brand' => 'WEBWORKS', 'title' => 'Judul', 'planned_at' => '2026-11-10T10:00'], ...$payload])
        ->assertSessionHasErrors($field);
})->with([
    'unknown brand' => [['brand' => 'NOPE'], 'brand'],
    'neither idea nor title' => [['title' => ''], 'idea'],
    'long idea' => [['title' => '', 'idea' => str_repeat('a', 1001)], 'idea'],
    'long title' => [['title' => str_repeat('a', 161)], 'title'],
    'bad time' => [['planned_at' => '10/11/2026'], 'planned_at'],
]);

it('edits a draft in place and records which fields changed', function (): void {
    $writer = admin();
    $item = draftItem($writer, complete: false);

    $this->actingAs($writer)->put("/content/items/{$item->public_id}", ['revision' => revisionId($item), 'caption' => '  Caption baru  ', 'brief' => ''])
        ->assertSessionHas('success');

    $revision = $item->refresh()->activeRevision;
    expect($revision?->number)->toBe(1)
        ->and($revision?->caption)->toBe('Caption baru')
        ->and($revision?->brief)->toBeNull()
        ->and(ContentRevision::query()->count())->toBe(1);
    $audit = AuditLog::query()->where('action', 'content.revised')->sole();
    expect($audit->new_state)->toMatchArray(['revision' => 1, 'fields' => ['caption'], 'image_changed' => false]);
});

it('refuses Ready until caption, alt text, and image are present, without needing a brief', function (): void {
    $writer = admin();
    $item = draftItem($writer, complete: false);

    $this->actingAs($writer)->post("/content/items/{$item->public_id}/ready", ['revision' => revisionId($item)])
        ->assertSessionHas('error', 'Lengkapi caption, alt text dan gambar sebelum menandai siap posting.');
    expect($item->refresh()->status)->toBe(ContentStatus::Draft);

    $image = postImage();
    $this->put("/content/items/{$item->public_id}", ['revision' => revisionId($item), ...completeContent(['brief' => '']), 'image' => $image])->assertSessionHas('success');
    $this->post("/content/items/{$item->public_id}/ready", ['revision' => revisionId($item)])->assertSessionHas('success');

    expect($item->refresh()->status)->toBe(ContentStatus::Ready)->and($item->activeRevision?->brief)->toBeNull();
});

it('lets one person take a post from draft to published, with no approval', function (): void {
    $writer = User::factory()->create(['name' => 'Penulis']);
    $item = draftItem($writer);

    $this->actingAs($writer)->post("/content/items/{$item->public_id}/ready", ['revision' => revisionId($item)])->assertSessionHas('success');
    $item->refresh();
    expect($item->status)->toBe(ContentStatus::Ready)
        ->and($item->ready_revision_id)->toBe($item->active_revision_id)
        ->and($item->approved_revision_id)->toBeNull()
        ->and($item->activeRevision?->isSubmitted())->toBeTrue()
        ->and($item->activeRevision?->submitted_by_user_id)->toBe($writer->id);

    $this->travel(10)->minutes();
    $postedAt = now()->subMinutes(2);
    $this->post("/content/items/{$item->public_id}/publication", [
        'revision' => revisionId($item),
        'published_at' => postedAtInput($postedAt),
        'url' => 'https://www.instagram.com/p/ABC123/',
        'checklist' => PostingQueue::CHECKLIST,
    ])->assertSessionHas('success', 'Konten ditandai sudah diposting.');

    $publication = ContentPublication::query()->sole();
    expect($item->refresh()->status)->toBe(ContentStatus::Published)
        ->and($publication->content_revision_id)->toBe($item->active_revision_id)
        ->and($publication->published_at->format('Y-m-d H:i'))->toBe($postedAt->utc()->format('Y-m-d H:i'))
        ->and($publication->actor_user_id)->toBe($writer->id)
        ->and(ContentApproval::query()->count())->toBe(0)
        ->and(contentAudit($item))->toBe(['content.created', 'content.revised', 'content.marked_ready', 'content.published']);
    $ready = AuditLog::query()->where('action', 'content.marked_ready')->sole();
    expect($ready->previous_state)->toBe(['status' => 'DRAFT'])->and($ready->new_state)->toMatchArray(['status' => 'READY', 'revision' => 1]);
});

it('allows a published mark without an Instagram URL but always with the posting time', function (): void {
    $item = readyItem();

    $this->actingAs(admin())->post("/content/items/{$item->public_id}/publication", ['revision' => revisionId($item)])->assertSessionHasErrors('published_at');
    $this->post("/content/items/{$item->public_id}/publication", ['revision' => revisionId($item), 'published_at' => postedAtInput(now()), 'checklist' => PostingQueue::CHECKLIST])->assertSessionHas('success');

    expect(ContentPublication::query()->sole()->url)->toBeNull();
});

it('accepts only an https Instagram URL', function (string $url): void {
    $item = readyItem();

    $this->actingAs(admin())->post("/content/items/{$item->public_id}/publication", ['revision' => revisionId($item), 'published_at' => postedAtInput(now()), 'url' => $url, 'checklist' => PostingQueue::CHECKLIST])
        ->assertSessionHasErrors('url');
    expect($item->refresh()->status)->toBe(ContentStatus::Ready);
})->with(['http://www.instagram.com/p/x/', 'https://instagram.com.evil.test/p/x/', 'https://evil.test/www.instagram.com', 'javascript:alert(1)', 'https://user@www.instagram.com/p/x/']);

it('refuses a posting time in the future or before the item was marked Ready', function (): void {
    $item = readyItem();
    $uri = "/content/items/{$item->public_id}/publication";

    $this->actingAs(admin())->post($uri, ['revision' => revisionId($item), 'published_at' => postedAtInput(now()->addHour()), 'checklist' => PostingQueue::CHECKLIST])
        ->assertSessionHas('error', 'Waktu posting tidak boleh di masa depan. Tandai setelah konten benar-benar diposting.');
    $this->post($uri, ['revision' => revisionId($item), 'published_at' => postedAtInput(now()->subDay()), 'checklist' => PostingQueue::CHECKLIST])
        ->assertSessionHas('error', 'Waktu posting tidak boleh sebelum konten ditandai siap posting.');

    expect($item->refresh()->status)->toBe(ContentStatus::Ready)->and(ContentPublication::query()->count())->toBe(0);
});

it('refuses a published mark on a Draft', function (): void {
    $item = draftItem();

    $this->actingAs(admin())->post("/content/items/{$item->public_id}/publication", ['revision' => revisionId($item), 'published_at' => postedAtInput(now()), 'checklist' => PostingQueue::CHECKLIST])
        ->assertSessionHas('error', 'Tandai siap posting dulu sebelum mencatat posting.');
    expect($item->refresh()->status)->toBe(ContentStatus::Draft)->and(ContentPublication::query()->count())->toBe(0);
});

it('never changes a status because the planned time passed', function (): void {
    $draft = draftItem();
    $ready = readyItem();

    $this->travelTo($ready->planned_at->addWeek());
    $this->artisan('schedule:run')->assertSuccessful();
    contentPage('/content/calendar');
    contentPage('/content/calendar?view=list&status=READY');

    expect($draft->refresh()->status)->toBe(ContentStatus::Draft)
        ->and($ready->refresh()->status)->toBe(ContentStatus::Ready)
        ->and(ContentPublication::query()->count())->toBe(0);
});

it('keeps frozen revisions immutable', function (): void {
    $item = readyItem();
    $revision = $item->activeRevision;

    expect(fn () => $revision?->update(['caption' => 'diam-diam']))->toThrow(LogicException::class)
        ->and(fn () => $revision?->delete())->toThrow(LogicException::class)
        ->and(fn () => ContentApproval::query()->create(['content_revision_id' => $revision?->id, 'decision' => ContentDecision::Approved, 'actor_user_id' => admin()->id])->update(['note' => 'x']))
        ->toThrow(LogicException::class);
});

it('edits Ready content as a new revision and returns it to Draft', function (): void {
    $writer = admin();
    $item = readyItem($writer);
    $ready = revisionId($item);

    $this->actingAs($writer)->put("/content/items/{$item->public_id}", ['revision' => $ready, 'caption' => 'Caption sesudah siap'])
        ->assertSessionHas('success', 'Revisi 2 disimpan dan konten kembali ke Draft. Tandai siap posting lagi setelah selesai.');

    $item->refresh();
    expect($item->status)->toBe(ContentStatus::Draft)
        ->and($item->ready_revision_id)->toBeNull()
        ->and($item->activeRevision?->number)->toBe(2)
        ->and($item->activeRevision?->isSubmitted())->toBeFalse()
        ->and(ContentRevision::query()->where('number', 1)->sole()->caption)->toBe('Website yang rapi membantu pelanggan percaya.');
    $audit = AuditLog::query()->where('action', 'content.revised')->latest('id')->first();
    expect($audit?->previous_state)->toEqual(['revision' => 1, 'status' => 'READY'])
        ->and($audit?->new_state)->toMatchArray(['revision' => 2, 'status' => 'DRAFT', 'fields' => ['caption']]);

    // The old revision can no longer be posted, and the new one must be marked Ready first.
    $this->post("/content/items/{$item->public_id}/publication", ['revision' => $ready, 'published_at' => postedAtInput(now()), 'checklist' => PostingQueue::CHECKLIST])
        ->assertSessionHas('error', ContentActionRefused::staleRevision()->getMessage());
    $this->post("/content/items/{$item->public_id}/publication", ['revision' => revisionId($item), 'published_at' => postedAtInput(now()), 'checklist' => PostingQueue::CHECKLIST])
        ->assertSessionHas('error', 'Tandai siap posting dulu sebelum mencatat posting.');

    $this->post("/content/items/{$item->public_id}/ready", ['revision' => revisionId($item)])->assertSessionHas('success');
    expect($item->refresh()->status)->toBe(ContentStatus::Ready)->and($item->ready_revision_id)->toBe($item->active_revision_id);
});

it('lets an edit to Ready content remove required fields, because the result is a Draft', function (): void {
    $item = readyItem();

    $this->actingAs(admin())->put("/content/items/{$item->public_id}", ['revision' => revisionId($item), 'caption' => ''])->assertSessionHas('success');

    expect($item->refresh()->status)->toBe(ContentStatus::Draft)
        ->and($item->activeRevision?->caption)->toBeNull()
        ->and(contentPage("/content/items/{$item->public_id}")['actions']['markReady'])->toBe('Lengkapi caption sebelum menandai siap posting.');
});

it('refuses a save of Ready content that changes nothing', function (): void {
    $item = readyItem();

    $this->actingAs(admin())->put("/content/items/{$item->public_id}", ['revision' => revisionId($item), ...completeContent()])
        ->assertSessionHas('error', 'Tidak ada perubahan untuk disimpan.');
    expect($item->refresh()->status)->toBe(ContentStatus::Ready)->and(ContentRevision::query()->count())->toBe(1);
});

it('returns Ready content to Draft and marks the same revision Ready again', function (): void {
    $writer = admin();
    $item = readyItem($writer);
    $frozenAt = $item->activeRevision?->submitted_at;

    $this->actingAs($writer)->post("/content/items/{$item->public_id}/return-to-draft", ['revision' => revisionId($item)])->assertSessionHas('success', 'Konten kembali ke Draft.');
    expect($item->refresh()->status)->toBe(ContentStatus::Draft)
        ->and($item->ready_revision_id)->toBeNull()
        ->and($item->activeRevision?->isSubmitted())->toBeTrue();

    $this->travel(5)->minutes();
    $this->post("/content/items/{$item->public_id}/ready", ['revision' => revisionId($item)])->assertSessionHas('success');
    expect($item->refresh()->status)->toBe(ContentStatus::Ready)
        ->and($item->activeRevision?->submitted_at?->equalTo($frozenAt))->toBeTrue()
        ->and(ContentRevision::query()->count())->toBe(1)
        ->and(contentAudit($item))->toBe(['content.created', 'content.revised', 'content.marked_ready', 'content.returned_to_draft', 'content.marked_ready']);
});

it('archives a Draft or Ready item with an optional reason and keeps its history', function (): void {
    $draft = draftItem();
    $ready = readyItem();

    $this->actingAs(admin())->post("/content/items/{$draft->public_id}/archive", ['revision' => revisionId($draft)])->assertSessionHas('success');
    $this->post("/content/items/{$ready->public_id}/archive", ['revision' => revisionId($ready), 'reason' => 'Promo dibatalkan'])->assertSessionHas('success');

    expect($draft->refresh()->status)->toBe(ContentStatus::Archived)
        ->and($ready->refresh()->status)->toBe(ContentStatus::Archived)
        ->and($ready->ready_revision_id)->toBeNull()
        ->and(ContentRevision::query()->count())->toBe(2);
    $audit = AuditLog::query()->where('action', 'content.archived')->where('entity_id', $ready->public_id)->sole();
    expect($audit->previous_state)->toBe(['status' => 'READY'])->and($audit->reason)->toBe('Promo dibatalkan');

    $this->post("/content/items/{$ready->public_id}/archive", ['revision' => revisionId($ready), 'reason' => str_repeat('a', 501)])->assertSessionHasErrors('reason');
});

it('refuses edits and transitions once archived or published', function (): void {
    $user = admin();
    $archived = draftItem();
    $published = publishedItem();
    $this->actingAs($user)->post("/content/items/{$archived->public_id}/archive", ['revision' => revisionId($archived)]);

    $this->put("/content/items/{$archived->public_id}", ['revision' => revisionId($archived), 'caption' => 'Ubah'])
        ->assertSessionHas('error', 'Konten diarsipkan dan tidak dapat diubah.');
    $this->put("/content/items/{$archived->public_id}/details", ['title' => 'Baru', 'planned_at' => '2026-12-01T10:00'])
        ->assertSessionHas('error', 'Konten diarsipkan dan tidak dapat diubah.');
    $this->post("/content/items/{$archived->public_id}/ready", ['revision' => revisionId($archived)])
        ->assertSessionHas('error', 'Hanya Draft yang dapat ditandai siap posting.');
    $this->put("/content/items/{$published->public_id}", ['revision' => revisionId($published), 'caption' => 'Ubah'])
        ->assertSessionHas('error', 'Konten sudah diposting dan tidak dapat diubah.');
    $this->post("/content/items/{$published->public_id}/archive", ['revision' => revisionId($published)])
        ->assertSessionHas('error', 'Hanya Draft atau konten Siap posting yang dapat diarsipkan.');
    $this->post("/content/items/{$published->public_id}/return-to-draft", ['revision' => revisionId($published)])
        ->assertSessionHas('error', 'Hanya konten Siap posting yang dapat dikembalikan ke Draft.');

    expect($archived->refresh()->status)->toBe(ContentStatus::Archived)
        ->and($published->refresh()->status)->toBe(ContentStatus::Published)
        ->and($published->activeRevision?->caption)->toBe('Website yang rapi membantu pelanggan percaya.');
});

it('refuses a transition on a revision the person has not seen', function (string $route): void {
    $writer = admin();
    $item = readyItem($writer);
    $stale = revisionId($item);
    // Editing Ready content makes revision 2 (Draft); mark it Ready again for the routes that need Ready.
    $this->actingAs($writer)->put("/content/items/{$item->public_id}", ['revision' => $stale, 'caption' => 'Baru'])->assertSessionHas('success');
    if ($route !== 'ready') {
        $this->post("/content/items/{$item->public_id}/ready", ['revision' => revisionId($item)])->assertSessionHas('success');
    }
    $before = $item->refresh()->status;

    $this->post("/content/items/{$item->public_id}/{$route}", ['revision' => $stale])
        ->assertSessionHas('error', ContentActionRefused::staleRevision()->getMessage());
    expect($item->refresh()->status)->toBe($before);
})->with(['ready', 'return-to-draft', 'archive']);

it('answers the retired editorial routes without changing anything', function (string $route): void {
    $item = readyItem();
    $audit = contentAudit($item);

    $this->actingAs(admin())->post("/content/items/{$item->public_id}/{$route}", ['revision' => revisionId($item), 'note' => 'x'])
        ->assertSessionHas('error', 'Alur pengajuan dan persetujuan sudah tidak dipakai. Gunakan Siap posting.');

    expect($item->refresh()->status)->toBe(ContentStatus::Ready)
        ->and(ContentApproval::query()->count())->toBe(0)
        ->and(contentAudit($item))->toBe($audit);
})->with(['submit-review', 'request-revision', 'approve', 'reject']);

it('changes title and planned time without a new revision or leaving Ready', function (): void {
    $item = readyItem();

    $this->actingAs(admin())->put("/content/items/{$item->public_id}/details", ['title' => 'Judul baru', 'planned_at' => '2026-12-01T19:30'])->assertSessionHas('success');

    $item->refresh();
    expect($item->title)->toBe('Judul baru')
        ->and($item->planned_at->toIso8601ZuluString())->toBe('2026-12-01T12:30:00Z')
        ->and($item->status)->toBe(ContentStatus::Ready)
        ->and(ContentRevision::query()->count())->toBe(1)
        ->and(contentAudit($item))->toContain('content.details_updated');
});

it('tells the screen which actions are open and why the others are not', function (): void {
    $writer = admin();
    $draft = draftItem($writer, complete: false);
    $ready = readyItem($writer);

    $forDraft = contentPage("/content/items/{$draft->public_id}", $writer)['actions'];
    $forReady = contentPage("/content/items/{$ready->public_id}", admin())['actions'];

    expect(array_keys($forDraft))->toBe(['edit', 'editDetails', 'markReady', 'returnToDraft', 'archive', 'markPublished'])
        ->and($forDraft['markReady'])->toBe('Lengkapi caption, alt text dan gambar sebelum menandai siap posting.')
        ->and($forDraft['returnToDraft'])->toBe('Hanya konten Siap posting yang dapat dikembalikan ke Draft.')
        ->and($forDraft['markPublished'])->toBe('Tandai siap posting dulu sebelum mencatat posting.')
        ->and($forDraft['archive'])->toBeNull()
        ->and($forReady['edit'])->toBeNull()
        ->and($forReady['markReady'])->toBe('Hanya Draft yang dapat ditandai siap posting.')
        ->and($forReady['returnToDraft'])->toBeNull()
        ->and($forReady['markPublished'])->toBeNull();
});

it('keeps every content route behind sign-in and addresses items by public id', function (): void {
    $item = draftItem();

    $this->get('/content/calendar')->assertRedirect('/login');
    $this->get("/content/items/{$item->public_id}")->assertRedirect('/login');
    foreach (['ready', 'return-to-draft', 'archive', 'approve'] as $route) {
        $this->post("/content/items/{$item->public_id}/{$route}")->assertRedirect('/login');
    }
    $this->actingAs(admin())->get("/content/items/{$item->id}")->assertNotFound();
    $this->get('/content')->assertRedirect('/content/calendar');
});

it('refuses the lifecycle routes to a deactivated user', function (): void {
    $user = admin();
    $item = draftItem($user);
    $user->update(['is_active' => false]);

    $this->actingAs($user)->post("/content/items/{$item->public_id}/ready", ['revision' => revisionId($item)])->assertRedirect('/login');

    expect($item->refresh()->status)->toBe(ContentStatus::Draft);
    $this->assertGuest();
});
