<?php

declare(strict_types=1);

namespace App\Http\Workspace;

use App\Domain\Payments\Enums\ReconciliationGroup;
use App\Domain\Payments\Queries\ReconciliationReport;
use App\Http\Presenters\ReconciliationPresenter;
use App\Support\Paginated;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Transaksi → Perlu Ditinjau (formerly Rekonsiliasi). The decisions stay in ReconciliationReport; this only picks the
 * group to show. Without a group it opens the first group that needs action and has issues.
 */
final class AttentionView
{
    public function __construct(private readonly ReconciliationReport $report) {}

    /** @return array<string, mixed> */
    public function props(Request $request): array
    {
        $validated = $request->validate([
            'group' => ['nullable', Rule::enum(ReconciliationGroup::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $counts = $this->report->counts();
        $group = ReconciliationGroup::tryFrom((string) ($validated['group'] ?? '')) ?? $this->defaultGroup($counts);

        return [
            'group' => $group->value,
            'counts' => $counts,
            'needsAction' => array_values(array_map(static fn (ReconciliationGroup $g): string => $g->value, array_filter(ReconciliationGroup::cases(), static fn (ReconciliationGroup $g): bool => $g->needsAction()))),
            'thresholds' => config('platform.reconciliation'),
            'issues' => Paginated::present($this->report->paginate($group), ReconciliationPresenter::row(...)),
        ];
    }

    /** @param array<string, int> $counts */
    private function defaultGroup(array $counts): ReconciliationGroup
    {
        foreach (ReconciliationGroup::cases() as $group) {
            if ($group->needsAction() && ($counts[$group->value] ?? 0) > 0) {
                return $group;
            }
        }

        return ReconciliationGroup::OrdersWithoutAttempt;
    }
}
