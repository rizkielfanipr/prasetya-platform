<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentInsightSnapshot;
use App\Domain\Content\Models\ContentItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Records metrics a person read from Instagram for a published post (ADR-0011, phase 8). Never fetched from
 * Instagram, never edited: a later reading or a correction is another snapshot.
 */
final class RecordInsight
{
    public function __construct(private readonly RecordAudit $audit) {}

    /** @param  array<string, int|null>  $metrics  values for ContentInsightSnapshot::METRICS; reach is required */
    public function __invoke(ContentItem $item, CarbonInterface $measuredAt, array $metrics, ?string $note, User $actor): ContentInsightSnapshot
    {
        if ($item->status !== ContentStatus::Published) {
            throw ContentActionRefused::because('Insight hanya dapat dicatat untuk konten yang sudah diposting.');
        }
        $item->loadMissing('publication');
        if ($measuredAt->isAfter(now()->addMinutes(5))) {
            throw ContentActionRefused::because('Waktu pengukuran tidak boleh di masa depan.');
        }
        // Forms carry minutes, the record carries seconds: compare by the minute.
        if ($item->publication !== null && $measuredAt->isBefore($item->publication->published_at->copy()->startOfMinute())) {
            throw ContentActionRefused::because('Waktu pengukuran tidak boleh sebelum konten diposting.');
        }
        $values = [];
        foreach (ContentInsightSnapshot::METRICS as $metric) {
            $value = $metrics[$metric] ?? null;
            if ($value !== null && $value < 0) {
                throw ContentActionRefused::because('Angka insight tidak boleh negatif.');
            }
            $values[$metric] = $value;
        }
        if ($values['reach'] === null) {
            throw ContentActionRefused::because('Jangkauan wajib diisi.');
        }

        return DB::transaction(function () use ($item, $measuredAt, $values, $note, $actor): ContentInsightSnapshot {
            $snapshot = ContentInsightSnapshot::query()->create([
                ...$values,
                'content_item_id' => $item->id,
                'measured_at' => $measuredAt,
                'note' => $note === null || trim($note) === '' ? null : mb_substr(trim($note), 0, 500),
                'recorded_by_user_id' => $actor->id,
            ]);
            ($this->audit)('content.insight_recorded', 'content_item', $item->public_id, $actor, null, [
                'snapshot' => $snapshot->public_id,
                'measured_at' => $snapshot->measured_at->toIso8601ZuluString(),
                ...array_filter($values, static fn (?int $v): bool => $v !== null),
            ]);

            return $snapshot;
        });
    }
}
