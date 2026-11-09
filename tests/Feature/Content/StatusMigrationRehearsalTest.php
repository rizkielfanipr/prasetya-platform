<?php

declare(strict_types=1);

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Migration\ContentStatusAudit;
use App\Domain\Content\Migration\LegacyStatusMap;
use App\Domain\Content\Models\ContentApproval;
use App\Domain\Content\Models\ContentPublication;
use Illuminate\Support\Facades\DB;

/* ADR-0012, phase 0: the status migration is rehearsed on every legacy status before any status changes in code. */

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
        ->toEqualCanonicalizing(array_keys(LegacyStatusMap::FORWARD));

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

it('reports data that breaks an invariant instead of hiding it', function (): void {
    $items = legacyContentFixture();
    // Simulate damage outside the actions (for example a manual SQL edit).
    DB::table('content_items')->where('id', $items['DRAFT']->id)->update(['status' => 'APPROVED']);
    DB::table('content_items')->where('id', $items['REJECTED']->id)->update(['status' => 'SOMETHING_ELSE']);

    $report = app(ContentStatusAudit::class)->run();

    expect($report['issues']['approved_without_current_approval'])->toBe(1)
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
