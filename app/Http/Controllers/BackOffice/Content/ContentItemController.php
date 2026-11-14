<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Content;

use App\Domain\Audit\Queries\AuditTrail;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Actions\ArchiveContent;
use App\Domain\Content\Actions\CreateContentItem;
use App\Domain\Content\Actions\MarkContentPublished;
use App\Domain\Content\Actions\MarkContentReady;
use App\Domain\Content\Actions\RequestCopyGeneration;
use App\Domain\Content\Actions\ReturnContentToDraft;
use App\Domain\Content\Actions\SaveContentRevision;
use App\Domain\Content\Actions\UpdateContentDetails;
use App\Domain\Content\ContentAvailability;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Exceptions\InvalidContentImage;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentGenerationRun;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Support\ContentRuleValidator;
use App\Http\Presenters\ContentAiPresenter;
use App\Http\Presenters\ContentBrandPresenter;
use App\Http\Presenters\ContentPresenter;
use App\Http\Requests\BackOffice\Content\MarkContentPublishedRequest;
use App\Http\Requests\BackOffice\Content\SaveContentRevisionRequest;
use App\Http\Requests\BackOffice\Content\StoreContentItemRequest;
use App\Http\Requests\BackOffice\Content\UpdateContentDetailsRequest;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

/** Konten: the list, one item's workspace, and its workflow. Rules live in App\Domain\Content (ADR-0011). */
final class ContentItemController
{
    /**
     * The retired Konten list (ADR-0012, phase 2): the same search, filters, sort, and page now live in the Kalender's list
     * view. Unknown keys are dropped; the Kalender never redirects, so this cannot loop.
     */
    public function index(Request $request): RedirectResponse
    {
        $query = array_filter(
            $request->only(ContentCalendarController::LIST_KEYS),
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        );

        return redirect()->route('content.calendar', ['view' => 'list', ...$query]);
    }

    public function store(StoreContentItemRequest $request, CreateContentItem $create): RedirectResponse
    {
        $brand = ContentBrand::query()->where('code', (string) $request->validated('brand'))->firstOrFail();

        return $this->attempt(function () use ($create, $brand, $request): RedirectResponse {
            $item = $create($brand, $request->plan($brand), $this->actor($request), $request->idea());

            return redirect()->route('content.items.show', $item)->with('success', 'Draft dibuat. Lanjutkan dengan Buat dengan AI atau tulis caption sendiri.');
        });
    }

    /**
     * One item's workspace, shaped by its status (ADR-0013): Draft gets the editor, its brand check, and AI; Ready the
     * posting package; Published the posted revision and insight; Archived a read-only summary. Research, the full
     * revision history, AI runs, and the audit are loaded on demand by ContentItemEvidenceController.
     */
    public function show(Request $request, ContentItem $item, ContentAvailability $availability, AuditTrail $trail, ContentRuleValidator $rules, RequestCopyGeneration $generation): Response
    {
        $item->load([
            'brand.currentProfile', 'pillar', 'campaign', 'creator', 'revisions:id,public_id,content_item_id,number',
            ...array_map(static fn (string $relation): string => "activeRevision.{$relation}", ContentPresenter::REVISION_RELATIONS),
        ]);
        /** @var ContentRevision $active */
        $active = $item->activeRevision;
        $status = $item->status;
        $editable = in_array($status, [ContentStatus::Draft, ContentStatus::Ready], true);

        if ($status === ContentStatus::Published) {
            $item->load(['publication.actor', ...array_map(static fn (string $relation): string => "publication.revision.{$relation}", ContentPresenter::REVISION_RELATIONS)]);
        }
        if ($editable) {
            $item->load(['brand.pillars', 'brand.campaigns']);
        }

        return Inertia::render('content/ContentDetailPage', [
            'item' => ContentPresenter::item($item),
            'revision' => ContentPresenter::revision($active),
            'actions' => $availability->for($item, $this->actor($request)),
            'limits' => $editable ? ['maxHashtags' => SaveContentRevisionRequest::MAX_HASHTAGS, 'captionMax' => 2200] : null,
            'findings' => $editable ? ContentBrandPresenter::findings($rules->check($item, $active)) : null,
            'brandRulesVersion' => $editable ? $item->brand->currentProfile?->version : null,
            'planOptions' => $editable ? ContentBrandPresenter::planOptions($item->brand) : null,
            // AI only writes Drafts, so only a Draft gets its availability and latest run; the run history is evidence.
            'ai' => $status === ContentStatus::Draft ? ['unavailableReason' => $generation->reason($item), 'current' => $this->latestRun($item)] : null,
            // Evidence of the post comes from the revision that was posted, never from whatever revision is active now.
            'postedRevision' => $status === ContentStatus::Published && $item->publication !== null ? ContentPresenter::revision($item->publication->revision) : null,
            'insights' => $status === ContentStatus::Published
                ? $item->insights()->with('recordedBy')->limit(20)->get()->map(ContentPresenter::insight(...))->all()
                : null,
            'archive' => $status === ContentStatus::Archived ? $this->archiveEvidence($trail, $item) : null,
        ]);
    }

    public function update(SaveContentRevisionRequest $request, ContentItem $item, SaveContentRevision $save): RedirectResponse
    {
        /** @var array<string, string|null> $fields */
        $fields = $request->safe()->only(ContentRevision::FIELDS);
        $upload = $request->file('image');
        $image = $upload instanceof UploadedFile ? ['path' => (string) $upload->getRealPath(), 'name' => $upload->getClientOriginalName()] : null;

        return $this->attempt(function () use ($save, $item, $request, $fields, $image): RedirectResponse {
            $revision = $save($item, (string) $request->validated('revision'), $fields, $image, $this->actor($request));

            $wasReady = $item->status === ContentStatus::Ready;

            return back()->with('success', $wasReady && $item->refresh()->status === ContentStatus::Draft
                ? "Revisi {$revision->number} disimpan dan konten kembali ke Draft. Tandai siap posting lagi setelah selesai."
                : "Revisi {$revision->number} disimpan.");
        });
    }

    public function details(UpdateContentDetailsRequest $request, ContentItem $item, UpdateContentDetails $update): RedirectResponse
    {
        return $this->attempt(function () use ($update, $item, $request): RedirectResponse {
            $update($item, $request->plan(), $this->actor($request));

            return back()->with('success', 'Rencana konten diperbarui.');
        });
    }

    public function ready(Request $request, ContentItem $item, MarkContentReady $ready): RedirectResponse
    {
        $revision = $this->expectedRevision($request);

        return $this->attempt(function () use ($ready, $item, $revision, $request): RedirectResponse {
            $ready($item, $revision, $this->actor($request));

            return back()->with('success', 'Konten siap diposting. Buka paket posting saat jadwalnya tiba.');
        });
    }

    public function returnToDraft(Request $request, ContentItem $item, ReturnContentToDraft $return): RedirectResponse
    {
        $revision = $this->expectedRevision($request);

        return $this->attempt(function () use ($return, $item, $revision, $request): RedirectResponse {
            $return($item, $revision, $this->actor($request));

            return back()->with('success', 'Konten kembali ke Draft.');
        });
    }

    public function archive(Request $request, ContentItem $item, ArchiveContent $archive): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'string', 'size:26'], 'reason' => ['nullable', 'string', 'max:500']]);

        return $this->attempt(function () use ($archive, $item, $data, $request): RedirectResponse {
            $reason = $data['reason'] ?? null;
            $archive($item, (string) $data['revision'], is_string($reason) ? $reason : null, $this->actor($request));

            return back()->with('success', 'Konten diarsipkan. Riwayatnya tetap tersimpan.');
        });
    }

    /**
     * The editorial routes of ADR-0011 (submit, approve, reject, request revision) during the compatibility window:
     * they change nothing and explain where the action went (ADR-0012).
     */
    public function retiredReview(): RedirectResponse
    {
        return back()->with('error', 'Alur pengajuan dan persetujuan sudah tidak dipakai. Gunakan Siap posting.');
    }

    public function markPublished(MarkContentPublishedRequest $request, ContentItem $item, MarkContentPublished $mark): RedirectResponse
    {
        return $this->attempt(function () use ($mark, $item, $request): RedirectResponse {
            $mark(
                $item,
                (string) $request->validated('revision'),
                $request->publishedAt(),
                $request->validated('url') === null ? null : (string) $request->validated('url'),
                $request->validated('note') === null ? null : (string) $request->validated('note'),
                $this->actor($request),
                $request->checklist(),
            );

            return back()->with('success', 'Konten ditandai sudah diposting.');
        });
    }

    private function expectedRevision(Request $request): string
    {
        return (string) $request->validate(['revision' => ['required', 'string', 'size:26']])['revision'];
    }

    /** @return array<string, mixed>|null */
    private function latestRun(ContentItem $item): ?array
    {
        $run = ContentGenerationRun::query()->where('content_item_id', $item->id)->with(['item', 'requester', 'resultRevision'])->latest('id')->first();

        return $run === null ? null : ContentAiPresenter::run($run);
    }

    /**
     * Who archived the item, when, and why, from its latest `content.archived` audit entry (the reason is optional).
     *
     * @return array{at: string, actor: string, reason: string|null}|null
     */
    private function archiveEvidence(AuditTrail $trail, ContentItem $item): ?array
    {
        $entry = $trail->forContentItem($item->public_id)->where('action', 'content.archived')->latest('id')->first();

        return $entry === null ? null : ['at' => $entry->created_at->toIso8601ZuluString(), 'actor' => $entry->actor_label, 'reason' => $entry->reason];
    }

    /** @param  Closure(): RedirectResponse  $action */
    private function attempt(Closure $action): RedirectResponse
    {
        try {
            return $action();
        } catch (InvalidContentImage $invalid) {
            return back()->withErrors(['image' => $invalid->getMessage()]);
        } catch (ContentActionRefused $refused) {
            return back()->with('error', $refused->getMessage());
        }
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
