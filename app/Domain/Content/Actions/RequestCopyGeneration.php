<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Ai\CopyBudget;
use App\Domain\Content\Ai\CopyPromptBuilder;
use App\Domain\Content\Contracts\CopyGenerator;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Enums\CopyField;
use App\Domain\Content\Enums\GenerationStatus;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Jobs\GenerateContentCopy;
use App\Domain\Content\Models\ContentGenerationRun;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Support\ContentItemLock;
use App\Support\BusinessTime;
use Illuminate\Support\Facades\DB;

/**
 * Queues an AI draft of some copy fields for an item (ADR-0011, phase 3). The result is always a new draft revision;
 * AI never submits, approves, or publishes. One run at a time per item.
 */
final class RequestCopyGeneration
{
    /** A run still queued or running after this long is treated as lost (the worker stopped) and closed. */
    public const int STALE_MINUTES = 15;

    public function __construct(
        private readonly RecordAudit $audit,
        private readonly ContentItemLock $lock,
        private readonly CopyBudget $budget,
        private readonly CopyGenerator $generator,
    ) {}

    /**
     * @param  list<CopyField>  $fields
     * @param  string|null  $instruction  the writer's extra direction for this run, e.g. "lebih singkat"
     */
    public function __invoke(ContentItem $item, string $expectedRevision, array $fields, User $actor, ?string $instruction = null): ContentGenerationRun
    {
        $instruction = $instruction === null || trim($instruction) === '' ? null : mb_substr(trim($instruction), 0, 500);
        if ($fields === []) {
            throw ContentActionRefused::because('Pilih bagian yang ingin dibuat AI.');
        }

        return DB::transaction(function () use ($item, $expectedRevision, $fields, $actor, $instruction): ContentGenerationRun {
            $item = $this->lock->lock($item, $expectedRevision);
            $this->closeStaleRuns($item);
            $reason = $this->reason($item);
            if ($reason !== null) {
                throw ContentActionRefused::because($reason);
            }

            $run = ContentGenerationRun::query()->create([
                'content_item_id' => $item->id,
                'base_revision_id' => $item->active_revision_id,
                'requested_by_user_id' => $actor->id,
                'fields' => array_map(static fn (CopyField $f): string => $f->value, $fields),
                'instruction' => $instruction,
                // Reviewer notes belonged to the retired editorial workflow (ADR-0012); kept for legacy runs only.
                'used_review_note' => false,
                'status' => GenerationStatus::Queued,
                'provider' => $this->generator->provider(),
                'model' => $this->generator->model(),
                'prompt_version' => CopyPromptBuilder::VERSION,
            ]);

            ($this->audit)('content.generation_requested', 'content_item', $item->public_id, $actor, null, [
                'run' => $run->public_id,
                'fields' => $run->fields,
                'instruction' => $run->instruction,
                'used_review_note' => $run->used_review_note,
                'provider' => $run->provider,
                'model' => $run->model,
                'revision' => $item->activeRevision?->number,
            ]);

            GenerateContentCopy::dispatch($run->id)->onQueue((string) config('content.ai.queue'))->afterCommit();

            return $run;
        });
    }

    /** Why AI copy cannot be requested for this item now, or null. The screen shows the same answer. */
    public function reason(ContentItem $item): ?string
    {
        $configuration = $this->budget->unavailableReason();
        if ($configuration !== null) {
            return $configuration;
        }
        if ($item->status !== ContentStatus::Draft) {
            return 'AI hanya menulis konten berstatus Draft. Kembalikan ke Draft dulu untuk membuat ulang.';
        }
        if ($this->activeRun($item)) {
            return 'AI sedang membuat draft untuk konten ini.';
        }
        $until = $this->budget->circuitOpenUntil();
        if ($until !== null && $until->isFuture()) {
            return 'Provider AI gagal beberapa kali berturut-turut. Coba lagi setelah '.$until->setTimezone(BusinessTime::zone())->format('H:i').' WIB.';
        }

        return null;
    }

    private function activeRun(ContentItem $item): bool
    {
        return ContentGenerationRun::query()
            ->where('content_item_id', $item->id)
            ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Running])
            ->where('created_at', '>=', now()->subMinutes(self::STALE_MINUTES))
            ->exists();
    }

    private function closeStaleRuns(ContentItem $item): void
    {
        ContentGenerationRun::query()
            ->where('content_item_id', $item->id)
            ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Running])
            ->where('created_at', '<', now()->subMinutes(self::STALE_MINUTES))
            ->update([
                'status' => GenerationStatus::Failed,
                'error_code' => 'stalled',
                'error_message' => 'Tidak selesai dalam '.self::STALE_MINUTES.' menit. Pastikan queue worker AI berjalan.',
                'finished_at' => now(),
            ]);
    }
}
