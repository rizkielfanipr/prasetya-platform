<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Content;

use App\Domain\Auth\Models\User;
use App\Domain\Content\Actions\RequestCopyGeneration;
use App\Domain\Content\Ai\CopyBudget;
use App\Domain\Content\Contracts\CopyGenerator;
use App\Domain\Content\Enums\CopyField;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentGenerationRun;
use App\Domain\Content\Models\ContentItem;
use App\Http\Presenters\ContentAiPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** AI copy drafts (ADR-0011, phase 3): request one for an item, and Pengaturan AI. Keys are never shown or entered here. */
final class ContentAiController
{
    public function generate(Request $request, ContentItem $item, RequestCopyGeneration $requestGeneration): RedirectResponse
    {
        $data = $request->validate([
            'revision' => ['required', 'string', 'size:26'],
            'fields' => ['required', 'array', 'min:1', 'max:4'],
            'fields.*' => ['required', 'string', 'distinct', Rule::enum(CopyField::class)],
        ], ['fields.required' => 'Pilih bagian yang ingin dibuat AI.']);

        try {
            /** @var User $actor */
            $actor = $request->user();
            /** @var list<string> $fields */
            $fields = $data['fields'];
            $requestGeneration($item, (string) $data['revision'], array_map(static fn (string $f): CopyField => CopyField::from($f), $fields), $actor);
        } catch (ContentActionRefused $refused) {
            return back()->with('error', $refused->getMessage());
        }

        return back()->with('success', 'AI sedang membuat draft. Hasilnya menjadi revisi baru yang tetap perlu ditinjau.');
    }

    public function settings(CopyGenerator $generator, CopyBudget $budget): Response
    {
        return Inertia::render('content/ContentAiSettingsPage', [
            'status' => ContentAiPresenter::status($generator, $budget),
            'runs' => ContentGenerationRun::query()->with(['item', 'requester', 'resultRevision'])->latest('id')->limit(30)->get()
                ->map(ContentAiPresenter::run(...))->all(),
        ]);
    }
}
