<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Content;

use App\Domain\Auth\Models\User;
use App\Domain\Content\Actions\RecordInsight;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Support\PerformanceSummary;
use App\Http\Presenters\ContentPresenter;
use App\Http\Requests\BackOffice\Content\RecordInsightRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Insight (ADR-0011, phase 8): manual Instagram metrics and how posts performed. Nothing here changes strategy. */
final class ContentInsightController
{
    public const array WINDOWS = [30, 90, 180];

    public function index(Request $request, PerformanceSummary $summary): Response
    {
        $filters = $request->validate([
            'days' => ['nullable', 'integer', Rule::in(self::WINDOWS)],
            'brand' => ['nullable', 'string', 'max:32'],
        ]);
        $days = (int) ($filters['days'] ?? 90);
        $brand = is_string($filters['brand'] ?? null) ? ContentBrand::query()->where('code', $filters['brand'])->first() : null;

        return Inertia::render('content/ContentInsightsPage', [
            'filters' => ['days' => $days, 'brand' => $brand?->code],
            'windows' => self::WINDOWS,
            'brands' => ContentBrand::query()->orderBy('name')->get()->map(ContentPresenter::brand(...))->all(),
            'summary' => $summary->forBrand($brand?->id, $days),
        ]);
    }

    public function store(RecordInsightRequest $request, ContentItem $item, RecordInsight $record): RedirectResponse
    {
        try {
            $note = $request->validated('note');
            /** @var User $actor */
            $actor = $request->user();
            $record($item, $request->measuredAt(), $request->metrics(), is_string($note) ? $note : null, $actor);
        } catch (ContentActionRefused $refused) {
            return back()->with('error', $refused->getMessage());
        }

        return back()->with('success', 'Insight dicatat.');
    }
}
