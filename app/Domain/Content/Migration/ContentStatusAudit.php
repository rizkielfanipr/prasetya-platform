<?php

declare(strict_types=1);

namespace App\Domain\Content\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only facts about Content Studio data before the ADR-0012 migration: counts per status, revision links,
 * approval and publication evidence, and invariant breaches. Counts and ids only, never content text.
 */
final class ContentStatusAudit
{
    /**
     * @return array{
     *     statuses: array<string, int>, target: array<string, int>, unknown: array<string, int>,
     *     items: array{total: int, without_active_revision: int, approved_revision_set: int},
     *     approvals: array{total: int, by_decision: array<string, int>},
     *     publications: array{total: int, revisions: int},
     *     issues: array<string, int>
     * }
     */
    public function run(): array
    {
        /** @var array<string, int> $statuses */
        $statuses = DB::table('content_items')->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n): int => (int) $n)->all();
        $target = [];
        $unknown = [];
        foreach ($statuses as $status => $count) {
            // A legacy status maps forward; an already-migrated one is its own target.
            $mapped = LegacyStatusMap::FORWARD[$status] ?? (isset(LegacyStatusMap::BACKWARD[$status]) ? $status : null);
            if ($mapped === null) {
                $unknown[$status] = $count;
            } else {
                $target[$mapped] = ($target[$mapped] ?? 0) + $count;
            }
        }
        ksort($statuses);
        ksort($target);

        return [
            'statuses' => $statuses,
            'target' => $target,
            'unknown' => $unknown,
            'items' => [
                'total' => DB::table('content_items')->count(),
                'without_active_revision' => DB::table('content_items')->whereNull('active_revision_id')->count(),
                'approved_revision_set' => DB::table('content_items')->whereNotNull('approved_revision_id')->count(),
            ],
            'approvals' => [
                'total' => DB::table('content_approvals')->count(),
                'by_decision' => DB::table('content_approvals')->selectRaw('decision, count(*) as n')->groupBy('decision')->pluck('n', 'decision')->map(fn ($n): int => (int) $n)->all(),
            ],
            'publications' => [
                'total' => DB::table('content_publications')->count(),
                'revisions' => DB::table('content_publications')->distinct()->count('content_revision_id'),
            ],
            'issues' => $this->issues(),
        ];
    }

    /** @return array<string, int> invariant breaches, by kind; each should be 0 */
    private function issues(): array
    {
        $items = DB::table('content_items');

        return [
            // APPROVED must point at its current revision and have a real approval decision on it.
            'approved_without_current_approval' => (clone $items)->where('status', 'APPROVED')
                ->where(fn ($q) => $q->whereNull('approved_revision_id')->orWhereColumn('approved_revision_id', '!=', 'active_revision_id'))->count(),
            'approved_without_approval_row' => (clone $items)->where('status', 'APPROVED')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('content_approvals')
                ->whereColumn('content_approvals.content_revision_id', 'content_items.approved_revision_id')->where('decision', 'APPROVED'))->count(),
            // Legacy evidence: approved (now Ready) and published items may keep the approved revision; no other status does.
            // Under ADR-0012 a publication needs no approval, so a published item without one is valid.
            'approved_revision_set_on_draft_or_archived' => (clone $items)->whereNotNull('approved_revision_id')->whereNotIn('status', ['APPROVED', 'READY', 'PUBLISHED'])->count(),
            'active_revision_of_other_item' => (clone $items)->whereNotNull('active_revision_id')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('content_revisions')
                ->whereColumn('content_revisions.id', 'content_items.active_revision_id')->whereColumn('content_revisions.content_item_id', 'content_items.id'))->count(),
            'published_without_publication' => (clone $items)->where('status', 'PUBLISHED')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('content_publications')
                ->whereColumn('content_publications.content_item_id', 'content_items.id'))->count(),
            'publication_on_unpublished_item' => DB::table('content_publications')->join('content_items', 'content_items.id', '=', 'content_publications.content_item_id')
                ->where('content_items.status', '!=', 'PUBLISHED')->count(),
            // After the migration: Ready names its current revision. Before it (or after a rollback) there is no column and no READY.
            'ready_without_current_ready_revision' => Schema::hasColumn('content_items', 'ready_revision_id')
                ? (clone $items)->where('status', 'READY')
                    ->where(fn ($q) => $q->whereNull('ready_revision_id')->orWhereColumn('ready_revision_id', '!=', 'active_revision_id'))->count()
                : (clone $items)->where('status', 'READY')->count(),
            'publication_revision_of_other_item' => DB::table('content_publications')->join('content_revisions', 'content_revisions.id', '=', 'content_publications.content_revision_id')
                ->whereColumn('content_revisions.content_item_id', '!=', 'content_publications.content_item_id')->count(),
        ];
    }
}
