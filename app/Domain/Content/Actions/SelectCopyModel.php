<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Ai\CopyModels;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentAiSetting;
use Illuminate\Support\Facades\DB;

/** Chooses which allowlisted copy model AI drafts use. Audited; takes effect for the next run. */
final class SelectCopyModel
{
    public function __construct(private readonly RecordAudit $audit, private readonly CopyModels $models) {}

    public function __invoke(string $name, User $actor): void
    {
        if ($this->models->find($name) === null) {
            throw ContentActionRefused::because('Model ini tidak ada di daftar model yang diizinkan server.');
        }
        $previous = $this->models->selected()?->name;
        if ($previous === $name) {
            return;
        }

        DB::transaction(function () use ($name, $previous, $actor): void {
            ContentAiSetting::query()->updateOrCreate(['key' => ContentAiSetting::COPY_MODEL], ['value' => $name, 'updated_by_user_id' => $actor->id]);
            ($this->audit)('content.ai_model_selected', 'content_ai', 'copy', $actor, ['model' => $previous], ['model' => $name]);
        });
    }
}
