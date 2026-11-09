<?php

declare(strict_types=1);

namespace App\Domain\Content\Migration;

use Illuminate\Support\Facades\DB;

/**
 * The data half of the ADR-0012 lifecycle migration, kept apart from the schema change so it can be rehearsed and
 * tested on its own. Both directions are idempotent: every update matches only the values it converts.
 *
 * Never touched: approved_revision_id, content_approvals (decisions, notes, areas), revisions, publications, checklist
 * evidence, timestamps, or actors. Rollback restores compatible statuses and never creates an approval.
 */
final class LegacyStatusBackfill
{
    /** @return array<string, int> rows changed per legacy status (plus `ready_revision_id` backfilled) */
    public function forward(): array
    {
        return DB::transaction(static function (): array {
            $changed = [
                // An APPROVED item becomes Ready on its current revision when that revision is frozen and is not
                // contradicted by an approval of another revision: approved under ADR-0011 (approved = active), or
                // marked Ready under ADR-0012 and then rolled back (no approval, which the audit reports). An item
                // approved on an older revision keeps ready_revision_id empty, stays unpostable, and is reported.
                'ready_revision_id' => DB::table('content_items')->where('status', 'APPROVED')->whereNull('ready_revision_id')
                    ->where(static fn ($q) => $q->whereNull('approved_revision_id')->orWhereColumn('approved_revision_id', 'active_revision_id'))
                    ->whereExists(static fn ($q) => $q->selectRaw('1')->from('content_revisions')
                        ->whereColumn('content_revisions.id', 'content_items.active_revision_id')
                        ->whereColumn('content_revisions.content_item_id', 'content_items.id')
                        ->whereNotNull('content_revisions.submitted_at'))
                    ->update(['ready_revision_id' => DB::raw('active_revision_id')]),
            ];

            foreach (LegacyStatusMap::FORWARD as $legacy => $target) {
                if ($legacy !== $target) {
                    $changed[$legacy] = DB::table('content_items')->where('status', $legacy)->update(['status' => $target]);
                }
            }

            return $changed;
        });
    }

    /** @return array<string, int> rows changed per target status */
    public function backward(): array
    {
        return DB::transaction(static function (): array {
            $changed = [];
            foreach (LegacyStatusMap::BACKWARD as $target => $legacy) {
                if ($legacy !== $target) {
                    $changed[$target] = DB::table('content_items')->where('status', $target)->update(['status' => $legacy]);
                }
            }

            return $changed;
        });
    }
}
