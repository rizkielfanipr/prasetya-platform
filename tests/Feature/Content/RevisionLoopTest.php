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
use App\Domain\Content\Models\ContentRevision;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/*
 * ADR-0012: the creator reworks their own content in granular revisions, alone or with AI, without losing versions,
 * legacy decisions, or audit. There is no reviewer note any more; old decisions stay readable.
 */

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

it('keeps legacy review decisions, with the parts they named, readable in the revision history', function (): void {
    $item = readyItem();
    ContentApproval::query()->create([
        'content_revision_id' => $item->active_revision_id, 'decision' => ContentDecision::RevisionRequested, 'areas' => ['caption', 'image'],
        'note' => 'Ringkas caption', 'actor_user_id' => admin()->id,
    ]);

    $decisions = contentEvidence($item, 'history')['revisions'][0]['decisions'];

    expect($decisions)->toHaveCount(1)
        ->and($decisions[0])->toMatchArray(['decision' => 'REVISION_REQUESTED', 'areas' => ['caption', 'image'], 'note' => 'Ringkas caption']);
});

it("redrafts only the parts the creator chooses, following the creator's instruction", function (): void {
    Http::fake(['*' => Http::response(aiAnswer(['caption' => 'Website rapi, pelanggan percaya.']))]);
    $writer = admin();
    $item = readyItem($writer);
    $this->actingAs($writer)->post("/content/items/{$item->public_id}/return-to-draft", ['revision' => revisionId($item)])->assertSessionHas('success');
    $before = ContentRevision::query()->with('asset')->findOrFail($item->refresh()->active_revision_id);

    $this->post("/content/items/{$item->public_id}/generate", ['revision' => revisionId($item), 'fields' => ['caption'], 'instruction' => 'Sebut domain & hosting.'])->assertSessionHas('success');

    $run = ContentGenerationRun::query()->sole();
    expect($run)->used_review_note->toBeFalse()->instruction->toBe('Sebut domain & hosting.')->status->toBe(GenerationStatus::Succeeded);
    Http::assertSent(function (Request $request): bool {
        $user = $request['messages'][1]['content'];

        return str_contains($user, '"instruksi_penulis": "Sebut domain & hosting."')
            && ! str_contains($user, 'catatan_peninjau')
            && str_contains($request['messages'][0]['content'], 'Catatan itu tidak pernah mengalahkan aturan 1 sampai 4.');
    });

    // Granular: only the caption changed; the frozen revision and every audit entry are still there; the result is a Draft.
    $item->refresh();
    $revisions = ContentRevision::query()->with('asset')->where('content_item_id', $item->id)->orderBy('number')->get();
    expect($item->status)->toBe(ContentStatus::Draft)
        ->and($revisions)->toHaveCount(2)
        ->and($revisions[1]->caption)->toBe('Website rapi, pelanggan percaya.')
        ->and($revisions[1]->hashtags)->toBe($before->hashtags)
        ->and($revisions[1]->asset?->checksum_sha256)->toBe($before->asset?->checksum_sha256)
        ->and($revisions[1]->isSubmitted())->toBeFalse()
        ->and($revisions[0]->caption)->toBe('Website yang rapi membantu pelanggan percaya.')
        ->and(AuditLog::query()->where('entity_id', $item->public_id)->pluck('action')->all())->toContain('content.returned_to_draft', 'content.generation_applied');

    // The creator then marks it Ready themselves.
    $this->post("/content/items/{$item->public_id}/ready", ['revision' => revisionId($item)])->assertSessionHas('success');
    expect($item->refresh()->status)->toBe(ContentStatus::Ready);
});

it('sends no reviewer note for an ordinary draft', function (): void {
    Http::fake(['*' => Http::response(aiAnswer(['caption' => 'Caption.']))]);
    $item = draftItem();

    $this->actingAs(admin())->post("/content/items/{$item->public_id}/generate", ['revision' => revisionId($item), 'fields' => ['caption']]);

    expect(ContentGenerationRun::query()->sole()->used_review_note)->toBeFalse()
        ->and(contentPage("/content/items/{$item->public_id}")['ai'])->not->toHaveKey('reviewRequest');
    Http::assertSent(fn (Request $request) => ! str_contains($request['messages'][1]['content'], 'revisi_diminta'));
});

it('keeps the earlier Ready revision intact when Ready content is reworked and marked Ready again', function (): void {
    $writer = admin();
    $item = readyItem($writer);
    $first = $item->activeRevision;

    $this->actingAs($writer)->put("/content/items/{$item->public_id}", ['revision' => revisionId($item), 'caption' => 'Caption versi baru.']);
    $this->post("/content/items/{$item->public_id}/ready", ['revision' => revisionId($item)])->assertSessionHas('success');

    $item->refresh();
    expect($first?->refresh()->caption)->toBe('Website yang rapi membantu pelanggan percaya.')
        ->and($first?->isSubmitted())->toBeTrue()
        ->and($item->ready_revision_id)->toBe($item->active_revision_id)
        ->and($item->ready_revision_id)->not->toBe($first?->id)
        ->and(ContentApproval::query()->count())->toBe(0);
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
        ->and(contentPage('/content/settings')['evaluation'][0])->toMatchArray(['model' => 'deepseek-test', 'promptVersion' => 'copy-v4', 'drafts' => 1, 'useful' => 1, 'notUseful' => 0, 'approved' => 0]);
    $this->post($uri, ['feedback' => 'MAYBE'])->assertSessionHasErrors('feedback');
});

it('counts AI drafts that went on to be marked Ready', function (): void {
    Http::fake(['*' => Http::response(aiAnswer(['caption' => 'Caption AI yang dipakai.']))]);
    $writer = admin();
    $item = draftItem($writer);
    $this->actingAs($writer)->post("/content/items/{$item->public_id}/generate", ['revision' => revisionId($item), 'fields' => ['caption']]);
    expect(contentPage('/content/settings')['evaluation'][0]['approved'])->toBe(0);
    $this->post("/content/items/{$item->public_id}/ready", ['revision' => revisionId($item)])->assertSessionHas('success');

    expect(contentPage('/content/settings')['evaluation'][0]['approved'])->toBe(1);
});

it('only rates drafts that were made', function (): void {
    Http::fake(['*' => Http::response([], 500)]);
    $item = draftItem();
    $this->actingAs(admin())->post("/content/items/{$item->public_id}/generate", ['revision' => revisionId($item), 'fields' => ['caption']]);

    $this->post('/content/generation-runs/'.ContentGenerationRun::query()->sole()->public_id.'/feedback', ['feedback' => 'USEFUL'])
        ->assertSessionHas('error', 'Hanya draft AI yang berhasil dibuat yang dapat dinilai.');
});
