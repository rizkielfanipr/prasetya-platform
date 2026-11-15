<?php

declare(strict_types=1);

namespace App\Http\Workspace;

use App\Domain\Payments\Queries\ReconciliationReport;
use App\Http\Presenters\ReconciliationPresenter;
use App\Support\Paginated;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Transaksi → Perlu Ditinjau (ADR-0016): four review queues over the domain's reconciliation groups. The decisions stay
 * in ReconciliationReport; this only picks the queue to show. Without a queue it opens the first one with items.
 * A queue of several groups shows each as its own section, paginated separately (`page_<group>`).
 */
final class AttentionView
{
    public function __construct(private readonly ReconciliationReport $report) {}

    /** @return array<string, mixed> */
    public function props(Request $request): array
    {
        $validated = $request->validate([
            'queue' => ['nullable', Rule::enum(AttentionQueue::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $groupCounts = $this->report->counts();
        $queueCounts = [];
        foreach (AttentionQueue::cases() as $queue) {
            $queueCounts[$queue->value] = array_sum(array_map(static fn ($group): int => $groupCounts[$group->value] ?? 0, $queue->groups()));
        }

        $queue = AttentionQueue::tryFrom((string) ($validated['queue'] ?? '')) ?? $this->defaultQueue($queueCounts);
        $single = count($queue->groups()) === 1;

        return [
            'queue' => $queue->value,
            'queues' => array_values(array_map(static fn (AttentionQueue $q): string => $q->value, array_filter(
                AttentionQueue::cases(),
                static fn (AttentionQueue $q): bool => ! $q->hiddenWhenEmpty() || $queueCounts[$q->value] > 0 || $q === $queue,
            ))),
            'counts' => $queueCounts,
            'thresholds' => config('platform.reconciliation'),
            'sections' => array_map(fn ($group): array => [
                'group' => $group->value,
                'pageName' => $single ? 'page' : "page_{$group->value}",
                'issues' => Paginated::present($this->report->paginate($group, 15, $single ? 'page' : "page_{$group->value}"), ReconciliationPresenter::row(...)),
            ], array_values(array_filter($queue->groups(), static fn ($group): bool => $single || ($groupCounts[$group->value] ?? 0) > 0))),
        ];
    }

    /** @param array<string, int> $counts */
    private function defaultQueue(array $counts): AttentionQueue
    {
        foreach (AttentionQueue::cases() as $queue) {
            if ($counts[$queue->value] > 0) {
                return $queue;
            }
        }

        return AttentionQueue::Unmatched;
    }
}
