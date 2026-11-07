<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Content;

use App\Domain\Audit\Queries\AuditTrail;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Actions\AddContentFact;
use App\Domain\Content\Actions\RetireContentFact;
use App\Domain\Content\Actions\SaveCampaign;
use App\Domain\Content\Actions\SavePillar;
use App\Domain\Content\Actions\UpdateBrandProfile;
use App\Domain\Content\Enums\CampaignPriority;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentCampaign;
use App\Domain\Content\Models\ContentFact;
use App\Domain\Content\Models\ContentPillar;
use App\Http\Presenters\ContentBrandPresenter;
use App\Http\Presenters\ContentPresenter;
use App\Http\Presenters\TransactionPresenter;
use App\Http\Requests\BackOffice\Content\AddContentFactRequest;
use App\Http\Requests\BackOffice\Content\SaveCampaignRequest;
use App\Http\Requests\BackOffice\Content\SavePillarRequest;
use App\Http\Requests\BackOffice\Content\UpdateBrandRulesRequest;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Brand & Template: a brand's versioned rules, pillars, campaigns, and approved facts (ADR-0011, phase 2). */
final class ContentBrandController
{
    public const array TABS = ['rules', 'pillars', 'campaigns', 'facts', 'activity'];

    public function index(Request $request): RedirectResponse
    {
        $brand = ContentBrand::query()->orderByDesc('is_active')->orderBy('name')->firstOrFail();
        $tab = $request->query('tab');

        return redirect()->route('content.brands.show', ['brand' => $brand, ...(is_string($tab) && in_array($tab, self::TABS, true) ? ['tab' => $tab] : [])]);
    }

    public function show(Request $request, ContentBrand $brand, AuditTrail $trail): Response
    {
        $tab = $request->validate(['tab' => ['nullable', Rule::in(self::TABS)]])['tab'] ?? 'rules';
        $brand->load(['currentProfile.creator', 'pillars', 'campaigns']);

        return Inertia::render('content/ContentBrandPage', [
            'brand' => ContentPresenter::brand($brand) + ['id' => $brand->public_id],
            'brands' => ContentBrand::query()->orderBy('name')->get()->map(static fn (ContentBrand $b): array => ContentPresenter::brand($b) + ['id' => $b->public_id])->all(),
            'tab' => $tab,
            'rules' => $brand->currentProfile === null ? null : ContentBrandPresenter::rules($brand->currentProfile),
            'versions' => $brand->profiles()->with('creator')->limit(20)->get()->map(ContentBrandPresenter::version(...))->all(),
            'pillars' => $brand->pillars->map(ContentBrandPresenter::pillar(...))->all(),
            'campaigns' => $brand->campaigns->map(ContentBrandPresenter::campaign(...))->all(),
            'facts' => $brand->facts()->with(['campaign', 'creator', 'retiredBy'])->limit(200)->get()->map(ContentBrandPresenter::fact(...))->all(),
            'activity' => $tab === 'activity'
                ? $trail->forContentBrand($brand->public_id)->latest('id')->limit(100)->get()->map(TransactionPresenter::audit(...))->all()
                : [],
            'priorities' => array_map(static fn (CampaignPriority $p): string => $p->value, CampaignPriority::cases()),
        ]);
    }

    public function updateRules(UpdateBrandRulesRequest $request, ContentBrand $brand, UpdateBrandProfile $update): RedirectResponse
    {
        return $this->attempt(function () use ($request, $brand, $update): RedirectResponse {
            $profile = $update($brand, $request->safe()->except('change_note'), (string) $request->validated('change_note'), $this->actor($request));

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
            );

            return back()->with('success', 'Fakta ditambahkan.');
        });
    }

    public function retireFact(Request $request, ContentBrand $brand, ContentFact $fact, RetireContentFact $retire): RedirectResponse
    {
        $reason = (string) $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'Tulis alasan fakta ini tidak berlaku lagi.'])['reason'];

        return $this->attempt(function () use ($request, $brand, $fact, $retire, $reason): RedirectResponse {
            $retire($brand, $fact, $reason, $this->actor($request));

            return back()->with('success', 'Fakta tidak berlaku lagi.');
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
                CampaignPriority::from((string) $request->validated('priority')),
                $request->boolean('is_active'),
                $this->actor($request),
            );

            return back()->with('success', "Kampanye {$saved->name} disimpan.");
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
