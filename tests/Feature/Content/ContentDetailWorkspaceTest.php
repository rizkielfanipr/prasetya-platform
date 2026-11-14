<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Content\Actions\AddManualSource;
use App\Domain\Content\Actions\ArchiveContent;
use App\Domain\Content\Actions\MarkContentReady;
use App\Domain\Content\Actions\SaveContentRevision;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentRevision;
use Illuminate\Support\Facades\DB;

/* ADR-0013: the detail page sends what the status needs; evidence arrives on demand, read-only and scoped to the item. */

beforeEach(fn () => contentSetUp());

const EVIDENCE = ['revisions', 'activity', 'research', 'runs'];

it('sends a Draft its editor, brand check, and AI, but no evidence', function (): void {
    $props = contentPage('/content/items/'.draftItem()->public_id);

    expect($props)->toHaveKeys(['item', 'revision', 'actions', 'limits', 'findings', 'brandRulesVersion', 'planOptions', 'ai'])
        ->and($props['ai'])->toHaveKeys(['unavailableReason', 'current'])
        ->and($props['ai'])->not->toHaveKey('runs')
        ->and($props['postedRevision'])->toBeNull()
        ->and($props['insights'])->toBeNull()
        ->and($props['archive'])->toBeNull()
        ->and($props)->not->toHaveKeys(EVIDENCE);
});

it('sends a Ready item its posting package and edit context, without AI', function (): void {
    $props = contentPage('/content/items/'.readyItem()->public_id);

    expect($props['item']['status'])->toBe('READY')
        ->and($props['revision']['asset'])->not->toBeNull()
        ->and($props['findings'])->toBeArray()
        ->and($props['limits'])->not->toBeNull()
        ->and($props['ai'])->toBeNull()
        ->and($props['insights'])->toBeNull()
        ->and($props)->not->toHaveKeys(EVIDENCE);
});

it('sends a Published item its posted revision and insight, and nothing to edit', function (): void {
    $props = contentPage('/content/items/'.publishedItem()->public_id);

    expect($props['postedRevision']['number'])->toBe(1)
        ->and($props['insights'])->toBe([])
        ->and($props['findings'])->toBeNull()
        ->and($props['limits'])->toBeNull()
        ->and($props['planOptions'])->toBeNull()
        ->and($props['ai'])->toBeNull()
        ->and($props['actions']['edit'])->toBe('Konten sudah diposting dan tidak dapat diubah.')
        ->and($props)->not->toHaveKeys(EVIDENCE);
});

it('sends an Archived item its archive evidence and nothing to edit', function (): void {
    $item = draftItem();
    $archivist = admin();
    app(ArchiveContent::class)($item, revisionId($item), 'Digabung ke posting lain', $archivist);

    $props = contentPage("/content/items/{$item->public_id}");

    expect($props['archive'])->toMatchArray(['actor' => $archivist->name, 'reason' => 'Digabung ke posting lain'])
        ->and($props['findings'])->toBeNull()
        ->and($props['ai'])->toBeNull()
        ->and(collect($props['actions'])->only(['edit', 'markReady', 'archive', 'markPublished'])->filter()->count())->toBe(4);
});

it('shows a Published post from the revision that was posted, not from a later active revision', function (): void {
    $item = publishedItem();
    $posted = $item->publication()->sole()->content_revision_id;
    // Legacy data may carry a newer active revision than the posted one; the evidence must not follow it.
    $newer = DB::table('content_revisions')->insertGetId([
        'public_id' => (string) str()->ulid(), 'content_item_id' => $item->id, 'number' => 2, 'caption' => 'Revisi setelah posting',
        'author_user_id' => admin()->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('content_items')->where('id', $item->id)->update(['active_revision_id' => $newer]);

    $props = contentPage("/content/items/{$item->public_id}");

    expect($props['revision']['number'])->toBe(2)
        ->and($props['postedRevision']['id'])->toBe(ContentRevision::query()->findOrFail($posted)->public_id)
        ->and($props['postedRevision']['caption'])->toBe(completeContent()['caption'])
        ->and($props['item']['publication']['revisionNumber'])->toBe(1);
});

it('loads the revision history with legacy markers, AI provenance, and runs on demand', function (): void {
    $item = readyItem();
    app(SaveContentRevision::class)($item, revisionId($item), ['caption' => 'Caption kedua'], null, admin());

    $history = contentEvidence($item->refresh(), 'history');

    expect(collect($history['revisions'])->pluck('number')->all())->toBe([2, 1])
        ->and($history['revisions'][1]['submittedAt'])->not->toBeNull()
        ->and($history['readyRevisionId'])->toBeNull()
        ->and($history['approvedRevisionId'])->toBeNull()
        ->and($history['runs'])->toBe([]);
});

it('loads the item audit newest first, and research with its internal context, on demand', function (): void {
    $item = readyItem();

    expect(collect(contentEvidence($item, 'activity')['entries'])->pluck('action')->all())->toBe(['content.marked_ready', 'content.revised', 'content.created'])
        ->and(contentEvidence($item, 'research'))->toHaveKeys(['unavailableReason', 'cited', 'runs', 'internal', 'suggestedQuery']);
});

it('keeps the evidence of one item out of another', function (): void {
    $mine = draftItem(title: 'Konten saya');
    $other = draftItem(title: 'Konten lain');
    app(AddManualSource::class)($other, 'https://artikel.contoh.id/lain', 'Sumber konten lain', null, 'Untuk konten lain', admin());

    expect(contentEvidence($other, 'research')['cited'])->toHaveCount(1)
        ->and(contentEvidence($mine, 'research')['cited'])->toBe([])
        ->and(collect(contentEvidence($mine, 'activity')['entries'])->pluck('entityId')->unique()->all())->toBe([$mine->public_id])
        ->and(collect(contentEvidence($mine, 'history')['revisions'])->pluck('id')->all())->toBe([revisionId($mine)]);
});

it('refuses evidence to guests, deactivated users, and guessed ids', function (string $sheet): void {
    $item = draftItem();

    $this->getJson("/content/items/{$item->public_id}/{$sheet}")->assertUnauthorized();
    $this->actingAs(admin())->getJson("/content/items/01ZZZZZZZZZZZZZZZZZZZZZZZZ/{$sheet}")->assertNotFound();
    $this->actingAs(admin())->getJson("/content/items/{$item->id}/{$sheet}")->assertNotFound();

    $inactive = admin();
    $inactive->forceFill(['is_active' => false])->save();
    $this->actingAs($inactive)->getJson("/content/items/{$item->public_id}/{$sheet}")->assertRedirect('/login');
    $this->assertGuest();
})->with(['research', 'history', 'activity']);

it('changes nothing when evidence is read', function (): void {
    $item = readyItem();
    $before = [AuditLog::query()->count(), ContentRevision::query()->count(), $item->refresh()->updated_at?->toIso8601String(), $item->status];

    foreach (['research', 'history', 'activity'] as $sheet) {
        contentEvidence($item, $sheet);
    }

    expect([AuditLog::query()->count(), ContentRevision::query()->count(), $item->refresh()->updated_at?->toIso8601String(), $item->status])->toBe($before);
});

it('loads the page and the history with a fixed number of queries, however many revisions', function (): void {
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
    $this->actingAs($writer);

    $count = function (string $uri, bool $json = false): int {
        $json ? test()->getJson($uri)->assertOk() : test()->get($uri)->assertOk();
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $json ? test()->getJson($uri)->assertOk() : test()->get($uri)->assertOk();

        return $queries;
    };

    expect($count("/content/items/{$large->public_id}"))->toBe($count("/content/items/{$small->public_id}"))
        ->and($count("/content/items/{$large->public_id}/history", true))->toBe($count("/content/items/{$small->public_id}/history", true));
});
