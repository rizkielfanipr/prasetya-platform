<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Enums\ContentDecision;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentApproval;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentPublication;
use App\Domain\Content\Models\ContentRevision;
use App\Support\BusinessTime;
use Carbon\CarbonInterface;

/* ADR-0011: the manual Content Studio workflow, four-eyes approval, immutable revisions, and human publishing. */

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

it('validates a new item', function (array $payload, string $field): void {
    $this->actingAs(admin())->post('/content/items', [...['brand' => 'WEBWORKS', 'title' => 'Judul', 'planned_at' => '2026-11-10T10:00'], ...$payload])
        ->assertSessionHasErrors($field);
})->with([
    'unknown brand' => [['brand' => 'NOPE'], 'brand'],
    'missing title' => [['title' => ''], 'title'],
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

it('refuses to submit until brief, caption, alt text, and image are present', function (): void {
    $writer = admin();
    $item = draftItem($writer, complete: false);

    $this->actingAs($writer)->post("/content/items/{$item->public_id}/submit-review", ['revision' => revisionId($item)])
        ->assertSessionHas('error', 'Lengkapi brief, caption, alt text dan gambar sebelum mengajukan.');

    expect($item->refresh()->status)->toBe(ContentStatus::Draft);
});

it('runs one post from draft to published with two people', function (): void {
    $writer = User::factory()->create(['name' => 'Penulis']);
    $reviewer = User::factory()->create(['name' => 'Peninjau']);
    $item = draftItem($writer);

    $this->actingAs($writer)->post("/content/items/{$item->public_id}/submit-review", ['revision' => revisionId($item)])->assertSessionHas('success');
    expect($item->refresh()->status)->toBe(ContentStatus::ReadyForReview);

    // Four-eyes: the submitter cannot approve their own revision.
    $this->post("/content/items/{$item->public_id}/approve", ['revision' => revisionId($item)])
        ->assertSessionHas('error', 'Revisi ini Anda ajukan sendiri. Pengguna lain harus menyetujuinya.');
    expect($item->refresh()->status)->toBe(ContentStatus::ReadyForReview);

    $this->actingAs($reviewer)->post("/content/items/{$item->public_id}/approve", ['revision' => revisionId($item), 'note' => 'Siap tayang'])->assertSessionHas('success');
    expect($item->refresh()->status)->toBe(ContentStatus::Approved)
        ->and($item->approved_revision_id)->toBe($item->active_revision_id);

    $this->travel(10)->minutes();
    $postedAt = now()->subMinutes(2);
    $this->actingAs($writer)->post("/content/items/{$item->public_id}/publication", [
        'revision' => revisionId($item),
        'published_at' => postedAtInput($postedAt),
        'url' => 'https://www.instagram.com/p/ABC123/',
    ])->assertSessionHas('success');

    $publication = ContentPublication::query()->sole();
    expect($item->refresh()->status)->toBe(ContentStatus::Published)
        ->and($publication->content_revision_id)->toBe($item->active_revision_id)
        ->and($publication->published_at->format('Y-m-d H:i'))->toBe($postedAt->utc()->format('Y-m-d H:i'))
        ->and($publication->actor_user_id)->toBe($writer->id)
        ->and(ContentApproval::query()->sole()->decision)->toBe(ContentDecision::Approved)
        ->and(contentAudit($item))->toBe(['content.created', 'content.revised', 'content.submitted', 'content.approved', 'content.published']);
});

it('allows a published mark without an Instagram URL but always with the posting time', function (): void {
    $item = approvedItem();

    $this->actingAs(admin())->post("/content/items/{$item->public_id}/publication", ['revision' => revisionId($item)])->assertSessionHasErrors('published_at');
    $this->post("/content/items/{$item->public_id}/publication", ['revision' => revisionId($item), 'published_at' => postedAtInput(now())])->assertSessionHas('success');

    expect(ContentPublication::query()->sole()->url)->toBeNull();
});

it('accepts only an https Instagram URL', function (string $url): void {
    $item = approvedItem();

    $this->actingAs(admin())->post("/content/items/{$item->public_id}/publication", ['revision' => revisionId($item), 'published_at' => postedAtInput(now()), 'url' => $url])
        ->assertSessionHasErrors('url');
    expect($item->refresh()->status)->toBe(ContentStatus::Approved);
})->with(['http://www.instagram.com/p/x/', 'https://instagram.com.evil.test/p/x/', 'https://evil.test/www.instagram.com', 'javascript:alert(1)', 'https://user@www.instagram.com/p/x/']);

it('refuses a posting time in the future or before the approval', function (): void {
    $item = approvedItem();
    $uri = "/content/items/{$item->public_id}/publication";

    $this->actingAs(admin())->post($uri, ['revision' => revisionId($item), 'published_at' => postedAtInput(now()->addHour())])
        ->assertSessionHas('error', 'Waktu posting tidak boleh di masa depan. Tandai setelah konten benar-benar diposting.');
    $this->post($uri, ['revision' => revisionId($item), 'published_at' => postedAtInput(now()->subDay())])
        ->assertSessionHas('error', 'Waktu posting tidak boleh sebelum revisi ini disetujui.');

    expect($item->refresh()->status)->toBe(ContentStatus::Approved)->and(ContentPublication::query()->count())->toBe(0);
});

it('never publishes because the planned time passed', function (): void {
    $item = approvedItem();

    $this->travelTo($item->planned_at->addWeek());
    $this->artisan('schedule:run')->assertSuccessful();
    contentPage('/content/calendar');

    expect($item->refresh()->status)->toBe(ContentStatus::Approved)->and(ContentPublication::query()->count())->toBe(0);
});

it('keeps submitted revisions immutable', function (): void {
    $item = submittedItem();
    $revision = $item->activeRevision;

    expect(fn () => $revision?->update(['caption' => 'diam-diam']))->toThrow(LogicException::class)
        ->and(fn () => $revision?->delete())->toThrow(LogicException::class)
        ->and(fn () => ContentApproval::query()->create(['content_revision_id' => $revision?->id, 'decision' => ContentDecision::Approved, 'actor_user_id' => admin()->id])->update(['note' => 'x']))
        ->toThrow(LogicException::class);
});

it('refuses edits while an item is in review, rejected, or published', function (): void {
    $user = admin();
    $inReview = submittedItem();

    $this->actingAs($user)->put("/content/items/{$inReview->public_id}", ['revision' => revisionId($inReview), 'caption' => 'Ubah'])
        ->assertSessionHas('error', 'Konten sedang ditinjau. Minta revisi dulu untuk mengubahnya.');

    $this->post("/content/items/{$inReview->public_id}/reject", ['revision' => revisionId($inReview), 'note' => 'Tidak sesuai kampanye'])->assertSessionHas('success');
    $this->put("/content/items/{$inReview->public_id}", ['revision' => revisionId($inReview), 'caption' => 'Ubah'])
        ->assertSessionHas('error', 'Konten ditolak dan tidak dapat diubah.');
    $this->put("/content/items/{$inReview->public_id}/details", ['title' => 'Baru', 'planned_at' => '2026-12-01T10:00'])
        ->assertSessionHas('error', 'Judul dan jadwal tidak dapat diubah setelah konten ditolak.');

    expect($inReview->refresh()->activeRevision?->caption)->toBe('Website yang rapi membantu pelanggan percaya.');
});

it('needs a note to request a revision or reject', function (string $route): void {
    $item = submittedItem();

    $this->actingAs(admin())->post("/content/items/{$item->public_id}/{$route}", ['revision' => revisionId($item)])->assertSessionHasErrors('note');
    expect($item->refresh()->status)->toBe(ContentStatus::ReadyForReview);
})->with(['request-revision', 'reject']);

it('starts a new revision after a revision request and keeps editing it until resubmitted', function (): void {
    $writer = admin();
    $reviewer = admin();
    $item = submittedItem($writer);
    $first = $item->activeRevision;

    $this->actingAs($reviewer)->post("/content/items/{$item->public_id}/request-revision", ['revision' => revisionId($item), 'note' => 'Ringkas caption'])->assertSessionHas('success');
    expect($item->refresh()->status)->toBe(ContentStatus::RevisionRequested);

    $this->actingAs($writer)->post("/content/items/{$item->public_id}/submit-review", ['revision' => revisionId($item)])
        ->assertSessionHas('error', 'Ubah konten sesuai catatan revisi sebelum mengajukan lagi.');

    $this->put("/content/items/{$item->public_id}", ['revision' => revisionId($item), 'caption' => 'Caption ringkas'])->assertSessionHas('success');
    $second = $item->refresh()->activeRevision;
    expect($second?->number)->toBe(2)
        ->and($second?->brief)->toBe($first?->brief)
        ->and($second?->asset?->checksum_sha256)->toBe($first?->asset?->checksum_sha256)
        ->and($second?->asset?->id)->not->toBe($first?->asset?->id)
        ->and($first?->refresh()->caption)->toBe('Website yang rapi membantu pelanggan percaya.')
        ->and($item->status)->toBe(ContentStatus::RevisionRequested);

    $this->put("/content/items/{$item->public_id}", ['revision' => revisionId($item), 'hashtags' => '#baru'])->assertSessionHas('success');
    expect($item->refresh()->activeRevision?->number)->toBe(2)->and(ContentRevision::query()->count())->toBe(2);

    $this->post("/content/items/{$item->public_id}/submit-review", ['revision' => revisionId($item)])->assertSessionHas('success');
    expect($item->refresh()->status)->toBe(ContentStatus::ReadyForReview)
        ->and($item->activeRevision?->decisions)->toHaveCount(0)
        ->and($first?->decisions()->sole()->decision)->toBe(ContentDecision::RevisionRequested);
});

it('sends edited approved content back to review and voids the approval for publishing', function (): void {
    $writer = admin();
    $reviewer = admin();
    $editor = admin();
    $item = approvedItem($writer, $reviewer);
    $approved = revisionId($item);

    $this->actingAs($editor)->put("/content/items/{$item->public_id}", ['revision' => $approved, 'caption' => 'Caption sesudah disetujui'])->assertSessionHas('success');

    $item->refresh();
    expect($item->status)->toBe(ContentStatus::ReadyForReview)
        ->and($item->approved_revision_id)->toBeNull()
        ->and($item->activeRevision?->number)->toBe(2)
        ->and($item->activeRevision?->submitted_by_user_id)->toBe($editor->id);

    // The old approval cannot publish the new content, even with the old revision id.
    $this->post("/content/items/{$item->public_id}/publication", ['revision' => $approved, 'published_at' => postedAtInput(now())])
        ->assertSessionHas('error', ContentActionRefused::staleRevision()->getMessage());
    $this->post("/content/items/{$item->public_id}/publication", ['revision' => revisionId($item), 'published_at' => postedAtInput(now())])
        ->assertSessionHas('error', 'Hanya revisi terbaru yang sudah disetujui yang dapat ditandai dipublikasikan.');

    // The editor submitted revision 2, so someone else approves it; the original writer may.
    $this->post("/content/items/{$item->public_id}/approve", ['revision' => revisionId($item)])->assertSessionHas('error');
    $this->actingAs($writer)->post("/content/items/{$item->public_id}/approve", ['revision' => revisionId($item)])->assertSessionHas('success');
    expect($item->refresh()->status)->toBe(ContentStatus::Approved);
});

it('refuses an approved edit that removes required content', function (): void {
    $item = approvedItem();

    $this->actingAs(admin())->put("/content/items/{$item->public_id}", ['revision' => revisionId($item), 'caption' => ''])
        ->assertSessionHas('error', 'Lengkapi caption sebelum mengajukan.');

    expect($item->refresh()->status)->toBe(ContentStatus::Approved)->and(ContentRevision::query()->count())->toBe(1);
});

it('refuses a save of approved content that changes nothing', function (): void {
    $item = approvedItem();

    $this->actingAs(admin())->put("/content/items/{$item->public_id}", ['revision' => revisionId($item), ...completeContent()])
        ->assertSessionHas('error', 'Tidak ada perubahan untuk disimpan.');
    expect($item->refresh()->status)->toBe(ContentStatus::Approved);
});

it('refuses a decision on a revision the reviewer has not seen', function (): void {
    $writer = admin();
    $item = submittedItem($writer);
    $stale = revisionId($item);
    $this->actingAs(admin())->post("/content/items/{$item->public_id}/request-revision", ['revision' => $stale, 'note' => 'Ubah']);
    $this->actingAs($writer)->put("/content/items/{$item->public_id}", ['revision' => $stale, 'caption' => 'Baru'])->assertSessionHas('success');
    $this->post("/content/items/{$item->public_id}/submit-review", ['revision' => revisionId($item)]);

    $this->actingAs(admin())->post("/content/items/{$item->public_id}/approve", ['revision' => $stale])
        ->assertSessionHas('error', ContentActionRefused::staleRevision()->getMessage());
    expect($item->refresh()->status)->toBe(ContentStatus::ReadyForReview);
});

it('changes title and planned time without a new revision or a lost approval', function (): void {
    $item = approvedItem();

    $this->actingAs(admin())->put("/content/items/{$item->public_id}/details", ['title' => 'Judul baru', 'planned_at' => '2026-12-01T19:30'])->assertSessionHas('success');

    $item->refresh();
    expect($item->title)->toBe('Judul baru')
        ->and($item->planned_at->toIso8601ZuluString())->toBe('2026-12-01T12:30:00Z')
        ->and($item->status)->toBe(ContentStatus::Approved)
        ->and(ContentRevision::query()->count())->toBe(1)
        ->and(contentAudit($item))->toContain('content.details_updated');
});

it('tells the screen which actions are open and why the others are not', function (): void {
    $writer = admin();
    $item = submittedItem($writer);

    $mine = contentPage("/content/items/{$item->public_id}", $writer)['actions'];
    $theirs = contentPage("/content/items/{$item->public_id}", admin())['actions'];

    expect($mine['approve'])->toBe('Revisi ini Anda ajukan sendiri. Pengguna lain harus menyetujuinya.')
        ->and($mine['edit'])->toBe('Konten sedang ditinjau. Minta revisi dulu untuk mengubahnya.')
        ->and($theirs['approve'])->toBeNull()
        ->and($theirs['requestRevision'])->toBeNull()
        ->and($theirs['markPublished'])->not->toBeNull();
});

it('keeps every content route behind sign-in and addresses items by public id', function (): void {
    $item = draftItem();

    $this->get('/content/calendar')->assertRedirect('/login');
    $this->get("/content/items/{$item->public_id}")->assertRedirect('/login');
    $this->post("/content/items/{$item->public_id}/approve")->assertRedirect('/login');
    $this->actingAs(admin())->get("/content/items/{$item->id}")->assertNotFound();
    $this->get('/content')->assertRedirect('/content/calendar');
});
