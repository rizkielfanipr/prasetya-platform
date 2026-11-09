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
use App\Domain\Content\Actions\RequestResearch;
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
use App\Domain\Content\Models\ContentResearchRun;
use App\Domain\Content\Models\ContentResearchSource;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Research\InternalContext;
use App\Domain\Content\Support\ContentRuleValidator;
use App\Http\Presenters\ContentAiPresenter;
use App\Http\Presenters\ContentBrandPresenter;
use App\Http\Presenters\ContentPresenter;
use App\Http\Presenters\ContentResearchPresenter;
use App\Http\Presenters\TransactionPresenter;
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

    public function show(Request $request, ContentItem $item, ContentAvailability $availability, AuditTrail $trail, ContentRuleValidator $rules, RequestCopyGeneration $generation, RequestResearch $research, InternalContext $context): Response
    {
        $item->load([
            'brand.currentProfile', 'brand.pillars', 'brand.campaigns', 'pillar', 'campaign', 'creator', 'sources', 'publication.actor', 'publication.revision',
            'revisions.asset', 'revisions.decisions.actor', 'revisions.author', 'revisions.submitter', 'revisions.brandProfile', 'revisions.generationRun',
        ]);
        /** @var ContentRevision $active */
        $active = $item->revisions->firstWhere('id', $item->active_revision_id);
        $item->setRelation('activeRevision', $active);

        return Inertia::render('content/ContentDetailPage', [
            'item' => ContentPresenter::item($item),
            'revision' => ContentPresenter::revision($active),
            'revisions' => $item->revisions->map(ContentPresenter::revision(...))->values()->all(),
            'actions' => $availability->for($item, $this->actor($request)),
            'activity' => $trail->forContentItem($item->public_id)->latest('id')->limit(100)->get()->map(TransactionPresenter::audit(...))->all(),
            'limits' => ['maxHashtags' => SaveContentRevisionRequest::MAX_HASHTAGS, 'captionMax' => 2200],
            'findings' => ContentBrandPresenter::findings($rules->check($item, $active)),
            'brandRulesVersion' => $item->brand->currentProfile?->version,
            'planOptions' => ContentBrandPresenter::planOptions($item->brand),
            'research' => $this->research($item, $research, $context),
            'insights' => $item->status === ContentStatus::Published
                ? $item->insights()->with('recordedBy')->limit(20)->get()->map(ContentPresenter::insight(...))->all()
                : null,
            'ai' => [
                'unavailableReason' => $generation->reason($item),
                'runs' => ContentGenerationRun::query()->where('content_item_id', $item->id)->with(['item', 'requester', 'resultRevision'])->latest('id')->limit(10)->get()
                    ->map(ContentAiPresenter::run(...))->all(),
            ],
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

    /** @return array<string, mixed> */
    private function research(ContentItem $item, RequestResearch $research, InternalContext $context): array
    {
        $runs = ContentResearchRun::query()->where('content_item_id', $item->id)->with('requester')->latest('id')->limit(5)->get();
        $ids = $runs->flatMap(static fn (ContentResearchRun $run): array => $run->result_source_ids ?? [])->unique()->values()->all();
        $sources = ContentResearchSource::query()->whereIn('id', $ids)->get();

        return [
            'unavailableReason' => $research->reason($item),
            'cited' => $item->sources->map(static fn (ContentResearchSource $s): array => ContentResearchPresenter::source($s, $s->getRelationValue('pivot')?->getAttribute('note')))->values()->all(),
            'runs' => $runs->map(static fn (ContentResearchRun $run): array => ContentResearchPresenter::run($run, $sources))->all(),
            'internal' => $context->for($item),
            'suggestedQuery' => trim($item->topic ?: $item->title),
        ];
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
