<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Content;

use App\Domain\Audit\Queries\AuditTrail;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Actions\AddContentFact;
use App\Domain\Content\Actions\AssistBrandKnowledge;
use App\Domain\Content\Actions\RetireContentFact;
use App\Domain\Content\Actions\SaveBrandKnowledge;
use App\Domain\Content\Actions\SaveCampaign;
use App\Domain\Content\Actions\SavePillar;
use App\Domain\Content\Actions\UpdateBrandProfile;
use App\Domain\Content\Enums\ContentFactCategory;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Exceptions\InvalidBrandGuide;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentCampaign;
use App\Domain\Content\Models\ContentFact;
use App\Domain\Content\Models\ContentPillar;
use App\Domain\Content\Support\BrandGuideParser;
use App\Http\Presenters\ContentBrandPresenter;
use App\Http\Presenters\ContentPresenter;
use App\Http\Presenters\TransactionPresenter;
use App\Http\Requests\BackOffice\Content\AddContentFactRequest;
use App\Http\Requests\BackOffice\Content\AssistBrandKnowledgeRequest;
use App\Http\Requests\BackOffice\Content\SaveBrandKnowledgeRequest;
use App\Http\Requests\BackOffice\Content\SaveCampaignRequest;
use App\Http\Requests\BackOffice\Content\SavePillarRequest;
use App\Http\Requests\BackOffice\Content\UpdateBrandRulesRequest;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** One free-text Brand Knowledge document, plus compatibility writes for the earlier structured model (ADR-0011). */
final class ContentBrandController
{
    public function index(Request $request): RedirectResponse
    {
        $brand = ContentBrand::query()->orderByDesc('is_active')->orderBy('name')->firstOrFail();
        $tab = $request->query('tab');
        if ($tab === 'campaigns') {
            return redirect()->route('content.calendar', ['manage_campaigns' => 1, 'brand' => $brand->code]);
        }

        return redirect()->route('content.brands.show', ['brand' => $brand]);
    }

    public function show(Request $request, ContentBrand $brand, AuditTrail $trail): Response|RedirectResponse
    {
        if ($request->query('tab') === 'campaigns') {
            return redirect()->route('content.calendar', ['manage_campaigns' => 1, 'brand' => $brand->code]);
        }
        $brand->load(['currentKnowledge.creator', 'currentKnowledge.chunks']);

        return Inertia::render('content/BrandKnowledgePage', [
            'brand' => ContentPresenter::brand($brand) + ['id' => $brand->public_id],
            'brands' => ContentBrand::query()->orderBy('name')->get()->map(static fn (ContentBrand $b): array => ContentPresenter::brand($b) + ['id' => $b->public_id])->all(),
            'knowledge' => $brand->currentKnowledge === null ? null : ContentBrandPresenter::knowledge($brand->currentKnowledge),
            'versions' => $brand->knowledgeVersions()->with(['creator', 'chunks'])->limit(20)->get()->map(ContentBrandPresenter::knowledge(...))->all(),
            'activity' => $trail->forContentBrand($brand->public_id)->latest('id')->limit(100)->get()->map(TransactionPresenter::audit(...))->all(),
            'assistantDraft' => $request->session()->get('brand_knowledge_assistant_draft'),
        ]);
    }

    public function assistKnowledge(AssistBrandKnowledgeRequest $request, ContentBrand $brand, AssistBrandKnowledge $assist): RedirectResponse
    {
        return $this->attempt(function () use ($request, $brand, $assist): RedirectResponse {
            /** @var array<string, string|null> $answers */
            $answers = $request->validated('answers');
            $current = $request->validated('current_source_text');
            $draft = $assist($brand, $answers, is_string($current) ? $current : null, $this->actor($request));

            return back()
                ->with('success', 'Usulan AI sudah masuk ke editor. Periksa dan simpan jika sudah sesuai.')
                ->with('brand_knowledge_assistant_draft', ['text' => $draft, 'nonce' => (string) str()->ulid()]);
        });
    }

    public function updateKnowledge(SaveBrandKnowledgeRequest $request, ContentBrand $brand, SaveBrandKnowledge $save): RedirectResponse
    {
        return $this->attempt(function () use ($request, $brand, $save): RedirectResponse {
            $version = $save($brand, (string) $request->validated('source_text'), $this->actor($request));

            return back()->with('success', "Brand Knowledge disimpan sebagai versi {$version->version}.");
        });
    }

    public function updateRules(UpdateBrandRulesRequest $request, ContentBrand $brand, UpdateBrandProfile $update, BrandGuideParser $parser): RedirectResponse
    {
        try {
            $rules = $parser->parse((string) $request->validated('guide'));
        } catch (InvalidBrandGuide $invalid) {
            return back()->withErrors(['guide' => $invalid->getMessage()])->withInput();
        }

        return $this->attempt(function () use ($request, $brand, $update, $rules): RedirectResponse {
            $profile = $update($brand, $rules, (string) $request->validated('change_note'), $this->actor($request));

            return back()->with('success', "Aturan brand disimpan sebagai versi {$profile->version}.");
        });
    }

    public function storePillar(SavePillarRequest $request, ContentBrand $brand, SavePillar $save): RedirectResponse
    {
        return $this->savePillar($request, $brand, null, $save);
    }

    public function updatePillar(SavePillarRequest $request, ContentBrand $brand, ContentPillar $pillar, SavePillar $save): RedirectResponse
    {
        return $this->savePillar($request, $brand, $pillar, $save);
    }

    public function storeCampaign(SaveCampaignRequest $request, ContentBrand $brand, SaveCampaign $save): RedirectResponse
    {
        return $this->saveCampaign($request, $brand, null, $save);
    }

    public function updateCampaign(SaveCampaignRequest $request, ContentBrand $brand, ContentCampaign $campaign, SaveCampaign $save): RedirectResponse
    {
        return $this->saveCampaign($request, $brand, $campaign, $save);
    }

    public function storeFact(AddContentFactRequest $request, ContentBrand $brand, AddContentFact $add): RedirectResponse
    {
        $campaign = $request->validated('campaign');
        $validUntil = $request->validated('valid_until');

        return $this->attempt(function () use ($request, $brand, $add, $campaign, $validUntil): RedirectResponse {
            $add(
                $brand,
                is_string($campaign) ? ContentCampaign::query()->where('public_id', $campaign)->firstOrFail() : null,
                (string) $request->validated('statement'),
                (string) $request->validated('source'),
                is_string($validUntil) ? CarbonImmutable::parse($validUntil) : null,
                $this->actor($request),
                ContentFactCategory::from((string) $request->validated('category')),
            );

            return back()->with('success', 'Knowledge ditambahkan.');
        });
    }

    public function retireFact(Request $request, ContentBrand $brand, ContentFact $fact, RetireContentFact $retire): RedirectResponse
    {
        $reason = (string) $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'Tulis alasan fakta ini tidak berlaku lagi.'])['reason'];

        return $this->attempt(function () use ($request, $brand, $fact, $retire, $reason): RedirectResponse {
            $retire($brand, $fact, $reason, $this->actor($request));

            return back()->with('success', 'Knowledge dinonaktifkan.');
        });
    }

    private function savePillar(SavePillarRequest $request, ContentBrand $brand, ?ContentPillar $pillar, SavePillar $save): RedirectResponse
    {
        return $this->attempt(function () use ($request, $brand, $pillar, $save): RedirectResponse {
            $description = $request->validated('description');
            $saved = $save($brand, $pillar, (string) $request->validated('name'), is_string($description) ? $description : null, (int) $request->validated('target_share'), $request->boolean('is_active'), $this->actor($request));

            return back()->with('success', "Pilar {$saved->name} disimpan.");
        });
    }

    private function saveCampaign(SaveCampaignRequest $request, ContentBrand $brand, ?ContentCampaign $campaign, SaveCampaign $save): RedirectResponse
    {
        return $this->attempt(function () use ($request, $brand, $campaign, $save): RedirectResponse {
            $objective = $request->validated('objective');
            $saved = $save(
                $brand,
                $campaign,
                (string) $request->validated('name'),
                is_string($objective) ? $objective : null,
                CarbonImmutable::parse((string) $request->validated('starts_on')),
                CarbonImmutable::parse((string) $request->validated('ends_on')),
                $request->priority($campaign),
                $request->isActive($campaign),
                $this->actor($request),
            );

            return back()->with('success', "Campaign {$saved->name} disimpan.");
        });
    }

    /** @param  Closure(): RedirectResponse  $action */
    private function attempt(Closure $action): RedirectResponse
    {
        try {
            return $action();
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
