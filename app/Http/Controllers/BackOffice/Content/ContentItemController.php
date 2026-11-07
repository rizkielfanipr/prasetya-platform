<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Content;

use App\Domain\Audit\Queries\AuditTrail;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Actions\CreateContentItem;
use App\Domain\Content\Actions\MarkContentPublished;
use App\Domain\Content\Actions\RequestCopyGeneration;
use App\Domain\Content\Actions\ReviewContent;
use App\Domain\Content\Actions\SaveContentRevision;
use App\Domain\Content\Actions\SubmitContentForReview;
use App\Domain\Content\Actions\UpdateContentDetails;
use App\Domain\Content\ContentAvailability;
use App\Domain\Content\Enums\ContentDecision;
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
use App\Http\Presenters\TransactionPresenter;
use App\Http\Requests\BackOffice\Content\ContentFilterRequest;
use App\Http\Requests\BackOffice\Content\MarkContentPublishedRequest;
use App\Http\Requests\BackOffice\Content\ReviewContentRequest;
use App\Http\Requests\BackOffice\Content\SaveContentRevisionRequest;
use App\Http\Requests\BackOffice\Content\StoreContentItemRequest;
use App\Http\Requests\BackOffice\Content\UpdateContentDetailsRequest;
use App\Support\Paginated;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

/** Konten: the list, one item's workspace, and its workflow. Rules live in App\Domain\Content (ADR-0011). */
final class ContentItemController
{
    public function index(ContentFilterRequest $request): Response
    {
        /** @var array{brand?: string|null, pillar?: string|null, campaign?: string|null, status?: string|null, search?: string|null, sort?: string|null, direction?: string|null} $filters */
        $filters = $request->validated();
        $sort = $filters['sort'] ?? 'planned_at';
        $direction = $filters['direction'] ?? 'desc';

        $items = ContentCalendarController::filtered($filters)
            ->when($filters['search'] ?? null, static fn ($q, string $search) => $q->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->with(ContentCalendarController::ROW_RELATIONS)
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('content/ContentListPage', [
            'filters' => [
                ...ContentCalendarController::currentFilters($filters),
                'search' => $filters['search'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'items' => Paginated::present($items, ContentPresenter::row(...)),
            ...ContentCalendarController::options(),
        ]);
    }

    public function store(StoreContentItemRequest $request, CreateContentItem $create): RedirectResponse
    {
        $brand = ContentBrand::query()->where('code', (string) $request->validated('brand'))->firstOrFail();

        return $this->attempt(function () use ($create, $brand, $request): RedirectResponse {
            $item = $create($brand, $request->plan(), $this->actor($request));

            return redirect()->route('content.items.show', $item)->with('success', 'Konten dibuat sebagai draft.');
        });
    }

    public function show(Request $request, ContentItem $item, ContentAvailability $availability, AuditTrail $trail, ContentRuleValidator $rules, RequestCopyGeneration $generation): Response
    {
        $item->load([
            'brand.currentProfile', 'brand.pillars', 'brand.campaigns', 'pillar', 'campaign', 'creator', 'publication.actor', 'publication.revision',
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

            return back()->with('success', $item->refresh()->status === ContentStatus::ReadyForReview
                ? "Revisi {$revision->number} disimpan dan diajukan ulang untuk ditinjau. Persetujuan sebelumnya tidak berlaku lagi."
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

    public function submit(Request $request, ContentItem $item, SubmitContentForReview $submit): RedirectResponse
    {
        $revision = (string) $request->validate(['revision' => ['required', 'string', 'size:26']])['revision'];

        return $this->attempt(function () use ($submit, $item, $revision, $request): RedirectResponse {
            $submit($item, $revision, $this->actor($request));

            return back()->with('success', 'Konten diajukan untuk ditinjau. Pengguna lain perlu menyetujuinya.');
        });
    }

    public function requestRevision(ReviewContentRequest $request, ContentItem $item, ReviewContent $review): RedirectResponse
    {
        return $this->review($request, $item, $review, ContentDecision::RevisionRequested, 'Revisi diminta. Penulis dapat mengubah konten lalu mengajukan lagi.');
    }

    public function approve(ReviewContentRequest $request, ContentItem $item, ReviewContent $review): RedirectResponse
    {
        return $this->review($request, $item, $review, ContentDecision::Approved, 'Konten disetujui dan siap diposting.');
    }

    public function reject(ReviewContentRequest $request, ContentItem $item, ReviewContent $review): RedirectResponse
    {
        return $this->review($request, $item, $review, ContentDecision::Rejected, 'Konten ditolak.');
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
            );

            return back()->with('success', 'Konten ditandai sudah dipublikasikan.');
        });
    }

    private function review(ReviewContentRequest $request, ContentItem $item, ReviewContent $review, ContentDecision $decision, string $message): RedirectResponse
    {
        return $this->attempt(function () use ($review, $item, $request, $decision, $message): RedirectResponse {
            $note = $request->validated('note');
            $review($item, (string) $request->validated('revision'), $decision, $note === null ? null : (string) $note, $this->actor($request));

            return back()->with('success', $message);
        });
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
