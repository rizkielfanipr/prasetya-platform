<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Enums\GenerationFeedback;
use App\Domain\Content\Enums\GenerationStatus;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentGenerationRun;
use Illuminate\Support\Facades\DB;

/**
 * Records whether an AI draft helped, and why not. Used to evaluate providers, models, and prompt versions; it never
 * changes a prompt or a rule by itself (ADR-0011, phase 6). The latest verdict counts; each one is audited.
 */
final class RateGeneratedDraft
{
    public function __construct(private readonly RecordAudit $audit) {}

    public function __invoke(ContentGenerationRun $run, GenerationFeedback $feedback, ?string $reason, User $actor): void
    {
        if ($run->status !== GenerationStatus::Succeeded) {
            throw ContentActionRefused::because('Hanya draft AI yang berhasil dibuat yang dapat dinilai.');
        }
        $reason = $reason === null || trim($reason) === '' ? null : trim($reason);
        if ($feedback === GenerationFeedback::NotUseful && $reason === null) {
            throw ContentActionRefused::because('Tulis alasan draft AI ini kurang berguna.');
        }

        DB::transaction(function () use ($run, $feedback, $reason, $actor): void {
            $previous = $run->feedback?->value;
            $run->forceFill(['feedback' => $feedback, 'feedback_reason' => $reason, 'feedback_by_user_id' => $actor->id, 'feedback_at' => now()])->save();

            ($this->audit)('content.generation_feedback', 'content_item', $run->item->public_id, $actor,
                $previous === null ? null : ['feedback' => $previous],
                ['run' => $run->public_id, 'feedback' => $feedback->value, 'model' => $run->model, 'prompt_version' => $run->prompt_version],
                $reason,
            );
        });
    }
}
