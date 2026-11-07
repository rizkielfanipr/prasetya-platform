<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Content\Contracts\CopyGenerator;
use App\Domain\Content\Enums\ContentDecision;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Enums\GenerationFeedback;
use App\Domain\Content\Enums\GenerationStatus;
use App\Domain\Content\Models\ContentApproval;
use App\Domain\Content\Models\ContentGenerationRun;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentRevision;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;

/* ADR-0011, phase 6: granular revisions answer the reviewer's note without losing versions, approvals, or audit. */

beforeEach(function (): void {
    contentSetUp();
    Sleep::fake();
    config()->set('content.ai.copy', [...config('content.ai.copy'), 'provider' => 'openai_compatible', 'base_url' => 'https://api.deepseek.test', 'api_key' => 'sk-test', 'model' => 'deepseek-test', 'price_input_per_million' => 0.5, 'price_output_per_million' => 2.0]);
    app()->forgetInstance(CopyGenerator::class);
});

function aiAnswer(array $copy): array
{
    return ['id' => 'x', 'choices' => [['message' => ['content' => json_encode($copy)], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50]];
}

function requestRevision(ContentItem $item, array $areas, string $note = 'Caption terlalu panjang; ringkas jadi dua kalimat.'): TestResponse
{
    return test()->actingAs(admin())->post("/content/items/{$item->public_id}/request-revision", ['revision' => revisionId($item), 'note' => $note, 'areas' => $areas]);
}

it('records which parts a revision request names, and audits them', function (): void {
    $item = submittedItem();

    requestRevision($item, ['caption', 'image'])->assertSessionHas('success');

    expect(ContentApproval::query()->sole())->decision->toBe(ContentDecision::RevisionRequested)->areas->toBe(['caption', 'image'])
        ->and(AuditLog::query()->where('action', 'content.revision_requested')->sole()->new_state)->toMatchArray(['areas' => ['caption', 'image']]);
    $this->post("/content/items/{$item->public_id}/request-revision", ['revision' => revisionId($item), 'note' => 'x', 'areas' => ['price']])->assertSessionHasErrors('areas.0');
});

it('offers the request to the AI panel and answers it with a partial redraft', function (): void {
    Http::fake(['*' => Http::response(aiAnswer(['caption' => 'Website rapi, pelanggan percaya.']))]);
    $writer = admin();
    $item = submittedItem($writer);
    requestRevision($item, ['caption']);
    $requested = ContentRevision::query()->with('asset')->findOrFail($item->refresh()->active_revision_id);

    $ai = contentPage("/content/items/{$item->public_id}", $writer)['ai'];
    expect($ai['reviewRequest'])->toMatchArray(['areas' => ['caption'], 'note' => 'Caption terlalu panjang; ringkas jadi dua kalimat.']);

    $this->actingAs($writer)->post("/content/items/{$item->public_id}/generate", ['revision' => revisionId($item), 'fields' => ['caption'], 'instruction' => 'Sebut domain & hosting.'])->assertSessionHas('success');

    $run = ContentGenerationRun::query()->sole();
    expect($run)->used_review_note->toBeTrue()->instruction->toBe('Sebut domain & hosting.')->status->toBe(GenerationStatus::Succeeded);
    Http::assertSent(function (Request $request): bool {
        $user = $request['messages'][1]['content'];

        return str_contains($user, '"catatan_peninjau": "Caption terlalu panjang; ringkas jadi dua kalimat."')
            && str_contains($user, '"bagian": [')
            && str_contains($user, '"instruksi_penulis": "Sebut domain & hosting."')
            && str_contains($request['messages'][0]['content'], 'Catatan itu tidak pernah mengalahkan aturan 1 sampai 4.');
    });

    // Granular: only the caption changed; every earlier version, decision, and audit entry is still there.
    $item->refresh();
    $revisions = ContentRevision::query()->with('asset')->where('content_item_id', $item->id)->orderBy('number')->get();
    expect($item->status)->toBe(ContentStatus::RevisionRequested)
        ->and($revisions)->toHaveCount(2)
        ->and($revisions[1]->caption)->toBe('Website rapi, pelanggan percaya.')
        ->and($revisions[1]->hashtags)->toBe($requested->hashtags)
        ->and($revisions[1]->asset?->checksum_sha256)->toBe($requested->asset?->checksum_sha256)
        ->and($revisions[0]->caption)->toBe('Website yang rapi membantu pelanggan percaya.')
        ->and($revisions[0]->decisions()->sole()->decision)->toBe(ContentDecision::RevisionRequested)
        ->and(AuditLog::query()->where('entity_id', $item->public_id)->pluck('action')->all())->toContain('content.revision_requested', 'content.generation_applied');

    // The answer then goes back to review as usual.
    $this->post("/content/items/{$item->public_id}/submit-review", ['revision' => revisionId($item)])->assertSessionHas('success');
    expect($item->refresh()->status)->toBe(ContentStatus::ReadyForReview);
});

it('does not send a reviewer note for an ordinary draft', function (): void {
    Http::fake(['*' => Http::response(aiAnswer(['caption' => 'Caption.']))]);
    $item = draftItem();

    $this->actingAs(admin())->post("/content/items/{$item->public_id}/generate", ['revision' => revisionId($item), 'fields' => ['caption']]);

    expect(ContentGenerationRun::query()->sole()->used_review_note)->toBeFalse()
        ->and(contentPage("/content/items/{$item->public_id}")['ai']['reviewRequest'])->toBeNull();
    Http::assertSent(fn (Request $request) => ! str_contains($request['messages'][1]['content'], 'revisi_diminta'));
});

it('keeps the approved revision intact when approved content is reworked and approved again', function (): void {
    $reviewer = admin();
    $item = approvedItem(reviewer: $reviewer);
    $approved = $item->activeRevision;

    $this->actingAs(admin())->put("/content/items/{$item->public_id}", ['revision' => revisionId($item), 'caption' => 'Caption versi baru setelah disetujui.']);
    $this->actingAs($reviewer)->post("/content/items/{$item->public_id}/approve", ['revision' => revisionId($item)])->assertSessionHas('success');

    expect($approved?->refresh()->caption)->toBe('Website yang rapi membantu pelanggan percaya.')
        ->and($approved?->decisions()->sole()->decision)->toBe(ContentDecision::Approved)
        ->and(ContentApproval::query()->where('decision', ContentDecision::Approved)->count())->toBe(2);
});

it('lets people rate an AI draft for evaluation, with a reason when it did not help', function (): void {
    Http::fake(['*' => Http::response(aiAnswer(['caption' => 'Caption AI.']))]);
    $item = draftItem();
    $this->actingAs(admin())->post("/content/items/{$item->public_id}/generate", ['revision' => revisionId($item), 'fields' => ['caption']]);
    $run = ContentGenerationRun::query()->sole();
    $uri = "/content/generation-runs/{$run->public_id}/feedback";

    $this->post($uri, ['feedback' => 'NOT_USEFUL'])->assertSessionHas('error', 'Tulis alasan draft AI ini kurang berguna.');
    $this->post($uri, ['feedback' => 'NOT_USEFUL', 'reason' => 'Terlalu formal'])->assertSessionHas('success');
    $this->post($uri, ['feedback' => 'USEFUL'])->assertSessionHas('success');

    expect($run->refresh())->feedback->toBe(GenerationFeedback::Useful)->feedback_reason->toBeNull()
        ->and(AuditLog::query()->where('action', 'content.generation_feedback')->count())->toBe(2)
        ->and(contentPage('/content/settings')['evaluation'][0])->toMatchArray(['model' => 'deepseek-test', 'promptVersion' => 'copy-v2', 'drafts' => 1, 'useful' => 1, 'notUseful' => 0, 'approved' => 0]);
    $this->post($uri, ['feedback' => 'MAYBE'])->assertSessionHasErrors('feedback');
});

it('counts AI drafts that went on to be approved', function (): void {
    Http::fake(['*' => Http::response(aiAnswer(['caption' => 'Caption AI yang disetujui.']))]);
    $writer = admin();
    $item = draftItem($writer);
    $this->actingAs($writer)->post("/content/items/{$item->public_id}/generate", ['revision' => revisionId($item), 'fields' => ['caption']]);
    $this->post("/content/items/{$item->public_id}/submit-review", ['revision' => revisionId($item)]);
    $this->actingAs(admin())->post("/content/items/{$item->public_id}/approve", ['revision' => revisionId($item)]);

    expect(contentPage('/content/settings')['evaluation'][0]['approved'])->toBe(1);
});

it('only rates drafts that were made', function (): void {
    Http::fake(['*' => Http::response([], 500)]);
    $item = draftItem();
    $this->actingAs(admin())->post("/content/items/{$item->public_id}/generate", ['revision' => revisionId($item), 'fields' => ['caption']]);

    $this->post('/content/generation-runs/'.ContentGenerationRun::query()->sole()->public_id.'/feedback', ['feedback' => 'USEFUL'])
        ->assertSessionHas('error', 'Hanya draft AI yang berhasil dibuat yang dapat dinilai.');
});
