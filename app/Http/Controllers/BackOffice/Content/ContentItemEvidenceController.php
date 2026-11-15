<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Content;

use App\Domain\Audit\Queries\AuditTrail;
use App\Domain\Content\Models\ContentGenerationRun;
use App\Domain\Content\Models\ContentItem;
use App\Http\Presenters\ContentAiPresenter;
use App\Http\Presenters\ContentPresenter;
use App\Http\Presenters\TransactionPresenter;
use Illuminate\Http\JsonResponse;

/**
 * Read-only evidence of one item, loaded when a person opens its sheet on the detail page (ADR-0013): research and
 * sources, the full revision history with AI runs, and the item's audit. Same auth and active-user middleware as the
 * page; the item is resolved by public id, so another item's evidence is never returned. Nothing here writes.
 */
final class ContentItemEvidenceController
{
    /** Every revision, newest first, with legacy decisions and AI provenance, plus the item's last ten AI runs. */
    public function history(ContentItem $item): JsonResponse
    {
        $item->load(array_map(static fn (string $relation): string => "revisions.{$relation}", ContentPresenter::REVISION_RELATIONS));
        $find = static fn (?int $id): ?string => $id === null ? null : $item->revisions->firstWhere('id', $id)?->public_id;

        return response()->json([
            'revisions' => $item->revisions->map(ContentPresenter::revision(...))->values()->all(),
            'readyRevisionId' => $find($item->ready_revision_id),
            'approvedRevisionId' => $find($item->approved_revision_id),
            'runs' => ContentGenerationRun::query()->where('content_item_id', $item->id)->with(['item', 'requester', 'resultRevision'])->latest('id')->limit(10)->get()
                ->map(ContentAiPresenter::run(...))->all(),
        ]);
    }

    /** The item's own audit, newest first, at most 100 entries, redacted as everywhere else. */
    public function activity(ContentItem $item, AuditTrail $trail): JsonResponse
    {
        return response()->json([
            'entries' => $trail->forContentItem($item->public_id)->latest('id')->limit(100)->get()->map(TransactionPresenter::audit(...))->all(),
        ]);
    }
}
