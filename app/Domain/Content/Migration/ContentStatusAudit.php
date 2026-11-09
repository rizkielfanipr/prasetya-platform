<?php

declare(strict_types=1);

namespace App\Domain\Content\Migration;

use Illuminate\Support\Facades\DB;

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
            if (isset(LegacyStatusMap::FORWARD[$status])) {
                $mapped = LegacyStatusMap::forward($status);
                $target[$mapped] = ($target[$mapped] ?? 0) + $count;
            } else {
                $unknown[$status] = $count;
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
            // Published items keep the approved revision as evidence of what was posted; other statuses clear it.
            'approved_revision_set_outside_approved_or_published' => (clone $items)->whereNotNull('approved_revision_id')->whereNotIn('status', ['APPROVED', 'PUBLISHED'])->count(),
            'published_without_approved_revision' => (clone $items)->where('status', 'PUBLISHED')->whereNull('approved_revision_id')->count(),
            'active_revision_of_other_item' => (clone $items)->whereNotNull('active_revision_id')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('content_revisions')
                ->whereColumn('content_revisions.id', 'content_items.active_revision_id')->whereColumn('content_revisions.content_item_id', 'content_items.id'))->count(),
            'published_without_publication' => (clone $items)->where('status', 'PUBLISHED')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('content_publications')
                ->whereColumn('content_publications.content_item_id', 'content_items.id'))->count(),
            'publication_on_unpublished_item' => DB::table('content_publications')->join('content_items', 'content_items.id', '=', 'content_publications.content_item_id')
                ->where('content_items.status', '!=', 'PUBLISHED')->count(),
            'publication_revision_of_other_item' => DB::table('content_publications')->join('content_revisions', 'content_revisions.id', '=', 'content_publications.content_revision_id')
                ->whereColumn('content_revisions.content_item_id', '!=', 'content_publications.content_item_id')->count(),
        ];
    }
}
