<?php

declare(strict_types=1);

use App\Domain\Content\ContentAvailability;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Migration\ContentStatusAudit;
use App\Domain\Content\Migration\LegacyStatusBackfill;
use App\Domain\Content\Migration\LegacyStatusMap;
use App\Domain\Content\Models\ContentApproval;
use App\Domain\Content\Models\ContentPublication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * ADR-0012: the status migration rehearsed on every legacy status. The data half (LegacyStatusBackfill) runs here inside
 * the test transaction; the schema half (ready_revision_id) is rehearsed with migrate/rollback on a disposable database.
 */

/** @return array<int, array<string, mixed>> evidence the migration must never change */
function legacyEvidence(): array
{
    return [
        'approvals' => DB::table('content_approvals')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        'revisions' => DB::table('content_revisions')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        'publications' => DB::table('content_publications')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        'items' => DB::table('content_items')->orderBy('id')->get(['id', 'active_revision_id', 'approved_revision_id', 'created_by_user_id', 'created_at', 'updated_at'])->map(fn ($r) => (array) $r)->all(),
    ];
}

/** @return array<string, string> legacy key => current status */
function statusesOf(array $items): array
{
    return array_map(static fn ($item): string => (string) DB::table('content_items')->where('id', $item->id)->value('status'), $items);
}

beforeEach(fn () => contentSetUp());

it('maps every legacy status onto the creator-owned lifecycle', function (): void {
    expect(LegacyStatusMap::FORWARD)->toBe([
        'DRAFT' => 'DRAFT',
        'READY_FOR_REVIEW' => 'DRAFT',
        'REVISION_REQUESTED' => 'DRAFT',
        'APPROVED' => 'READY',
        'PUBLISHED' => 'PUBLISHED',
        'REJECTED' => 'ARCHIVED',
    ])->and(array_map(static fn (ContentStatus $s): string => $s->value, ContentStatus::cases()))
        ->toEqualCanonicalizing(array_values(array_unique(LegacyStatusMap::FORWARD)));

    expect(fn () => LegacyStatusMap::forward('MAYBE'))->toThrow(InvalidArgumentException::class);
});

it('rolls back without inventing evidence: review states stay Draft, Ready returns to Approved as a status only', function (): void {
    foreach (['DRAFT', 'APPROVED', 'PUBLISHED', 'REJECTED'] as $status) {
        expect(LegacyStatusMap::backward(LegacyStatusMap::forward($status)))->toBe($status);
    }
    // Lossy by design: a review or revision request is not reconstructed.
    expect(LegacyStatusMap::backward(LegacyStatusMap::forward('READY_FOR_REVIEW')))->toBe('DRAFT')
        ->and(LegacyStatusMap::backward(LegacyStatusMap::forward('REVISION_REQUESTED')))->toBe('DRAFT');
});

it('audits a database holding every legacy status, with all evidence queryable and no breach', function (): void {
    $items = legacyContentFixture();

    $report = app(ContentStatusAudit::class)->run();

    expect($report['statuses'])->toBe(['APPROVED' => 1, 'DRAFT' => 1, 'PUBLISHED' => 1, 'READY_FOR_REVIEW' => 1, 'REJECTED' => 1, 'REVISION_REQUESTED' => 1])
        ->and($report['target'])->toBe(['ARCHIVED' => 1, 'DRAFT' => 3, 'PUBLISHED' => 1, 'READY' => 1])
        ->and($report['unknown'])->toBe([])
        ->and(array_filter($report['issues']))->toBe([])
        ->and($report['approvals']['by_decision'])->toEqual(['APPROVED' => 2, 'REVISION_REQUESTED' => 1, 'REJECTED' => 1])
        ->and($report['publications'])->toBe(['total' => 1, 'revisions' => 1])
        ->and(ContentApproval::query()->whereHas('revision', fn ($q) => $q->where('content_item_id', $items['APPROVED']->id))->count())->toBe(1)
        ->and(ContentPublication::query()->where('content_item_id', $items['PUBLISHED']->id)->value('content_revision_id'))->toBe($items['PUBLISHED']->approved_revision_id);
});

it('migrates every legacy status forward, keeps all evidence, and passes the audit', function (): void {
    $items = legacyContentFixture();
    $evidence = legacyEvidence();

    $changed = app(LegacyStatusBackfill::class)->forward();

    expect($changed)->toBe(['ready_revision_id' => 1, 'READY_FOR_REVIEW' => 1, 'REVISION_REQUESTED' => 1, 'APPROVED' => 1, 'REJECTED' => 1])
        ->and(statusesOf($items))->toBe([
            'DRAFT' => 'DRAFT', 'READY_FOR_REVIEW' => 'DRAFT', 'REVISION_REQUESTED' => 'DRAFT',
            'APPROVED' => 'READY', 'PUBLISHED' => 'PUBLISHED', 'REJECTED' => 'ARCHIVED',
        ])
        ->and(DB::table('content_items')->where('id', $items['APPROVED']->id)->value('ready_revision_id'))->toBe($items['APPROVED']->approved_revision_id)
        ->and(DB::table('content_items')->whereNotNull('ready_revision_id')->count())->toBe(1)
        ->and(legacyEvidence())->toEqual($evidence);

    $report = app(ContentStatusAudit::class)->run();
    expect($report['statuses'])->toBe(['ARCHIVED' => 1, 'DRAFT' => 3, 'PUBLISHED' => 1, 'READY' => 1])
        ->and($report['target'])->toBe($report['statuses'])
        ->and(array_filter($report['issues']))->toBe([])
        ->and($report['approvals']['by_decision'])->toEqual(['APPROVED' => 2, 'REVISION_REQUESTED' => 1, 'REJECTED' => 1]);

    // Migrated items work in the new lifecycle: the Ready one can be posted, the Draft ones edited.
    $ready = $items['APPROVED']->refresh();
    expect($ready->status)->toBe(ContentStatus::Ready)->and($ready->isReadyOnCurrentRevision())->toBeTrue();
    $this->actingAs(admin())->put("/content/items/{$items['READY_FOR_REVIEW']->public_id}", ['revision' => revisionId($items['READY_FOR_REVIEW']), 'caption' => 'Lanjut ditulis'])
        ->assertSessionHas('success');
    expect($items['READY_FOR_REVIEW']->refresh()->activeRevision?->number)->toBe(2);
});

it('is idempotent: a second forward run changes nothing', function (): void {
    legacyContentFixture();
    app(LegacyStatusBackfill::class)->forward();
    $after = DB::table('content_items')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

    expect(array_filter(app(LegacyStatusBackfill::class)->forward()))->toBe([])
        ->and(DB::table('content_items')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all())->toEqual($after);
});

it('rolls back to compatible statuses without inventing an approval or a review state', function (): void {
    $items = legacyContentFixture();
    $evidence = legacyEvidence();
    app(LegacyStatusBackfill::class)->forward();
    // A post marked Ready after the migration has no approval; rollback must not make one up.
    $fresh = readyItem();

    app(LegacyStatusBackfill::class)->backward();

    expect(statusesOf($items))->toBe([
        'DRAFT' => 'DRAFT', 'READY_FOR_REVIEW' => 'DRAFT', 'REVISION_REQUESTED' => 'DRAFT',
        'APPROVED' => 'APPROVED', 'PUBLISHED' => 'PUBLISHED', 'REJECTED' => 'REJECTED',
    ])
        ->and(DB::table('content_items')->where('id', $fresh->id)->value('status'))->toBe('APPROVED')
        ->and(DB::table('content_approvals')->count())->toBe(count($evidence['approvals']))
        ->and(DB::table('content_approvals')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all())->toEqual($evidence['approvals']);
    // Honest: the audit names the rolled-back Ready post that has no approval evidence instead of hiding it.
    expect(app(ContentStatusAudit::class)->run()['issues']['approved_without_approval_row'])->toBe(1);
    expect(array_filter(app(LegacyStatusBackfill::class)->backward()))->toBe([]);

    // Forward again, as after the schema rollback dropped ready_revision_id: both Ready posts are Ready on their frozen revision.
    DB::table('content_items')->update(['ready_revision_id' => null]);
    app(LegacyStatusBackfill::class)->forward();
    expect($fresh->refresh()->isReadyOnCurrentRevision())->toBeTrue()
        ->and($items['APPROVED']->refresh()->isReadyOnCurrentRevision())->toBeTrue()
        ->and(array_filter(app(ContentStatusAudit::class)->run()['issues']))->toBe([]);
});

it('leaves an item approved on an older revision unpostable after the migration, and reports it', function (): void {
    $items = legacyContentFixture();
    $older = $items['APPROVED'];
    // Damage from outside the actions: a newer revision became active while the approval points at the older one.
    DB::table('content_items')->where('id', $older->id)->update(['active_revision_id' => DB::table('content_revisions')->insertGetId([
        'public_id' => (string) Str::ulid(), 'content_item_id' => $older->id, 'number' => 2, 'author_user_id' => $older->created_by_user_id,
        'caption' => 'Revisi baru tanpa persetujuan', 'created_at' => now(), 'updated_at' => now(),
    ])]);

    app(LegacyStatusBackfill::class)->forward();

    expect($older->refresh()->status)->toBe(ContentStatus::Ready)
        ->and($older->ready_revision_id)->toBeNull()
        ->and(app(ContentAvailability::class)->reason($older, admin(), 'markPublished'))->toBe('Tandai siap posting dulu sebelum mencatat posting.')
        ->and(app(ContentStatusAudit::class)->run()['issues']['ready_without_current_ready_revision'])->toBe(1);
});

it('reports data that breaks an invariant instead of hiding it', function (): void {
    $items = legacyContentFixture();
    $ready = readyItem();
    DB::table('content_items')->where('id', $ready->id)->update(['ready_revision_id' => null]);
    // Simulate damage outside the actions (for example a manual SQL edit).
    DB::table('content_items')->where('id', $items['DRAFT']->id)->update(['status' => 'APPROVED']);
    DB::table('content_items')->where('id', $items['REJECTED']->id)->update(['status' => 'SOMETHING_ELSE']);

    $report = app(ContentStatusAudit::class)->run();

    expect($report['issues']['approved_without_current_approval'])->toBe(1)
        ->and($report['issues']['ready_without_current_ready_revision'])->toBe(1)
        ->and($report['issues']['approved_without_approval_row'])->toBe(1)
        ->and($report['unknown'])->toBe(['SOMETHING_ELSE' => 1]);
    $this->artisan('content:status-audit')->assertFailed();
});

it('changes nothing when audited', function (): void {
    legacyContentFixture();
    $before = DB::table('content_items')->orderBy('id')->get(['id', 'status', 'active_revision_id', 'approved_revision_id', 'updated_at'])->toArray();

    $this->artisan('content:status-audit')->assertSuccessful();

    expect(DB::table('content_items')->orderBy('id')->get(['id', 'status', 'active_revision_id', 'approved_revision_id', 'updated_at'])->toArray())->toEqual($before);
});
