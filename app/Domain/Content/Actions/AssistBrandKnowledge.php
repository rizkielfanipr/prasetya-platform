<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Ai\BrandKnowledgeAssistantPrompt;
use App\Domain\Content\Ai\CopyBudget;
use App\Domain\Content\Ai\CopyProviderFailed;
use App\Domain\Content\Ai\GeneratedBrandKnowledge;
use App\Domain\Content\Contracts\CopyGenerator;
use App\Domain\Content\Enums\GenerationStatus;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentBrandKnowledgeAssist;

/** Calls the text provider once and returns a reviewable proposal; it never saves a Brand Knowledge version. */
final class AssistBrandKnowledge
{
    public function __construct(
        private readonly RecordAudit $audit,
        private readonly CopyGenerator $generator,
        private readonly CopyBudget $budget,
        private readonly BrandKnowledgeAssistantPrompt $prompts,
    ) {}

    /** @param array<string, string|null> $rawAnswers */
    public function __invoke(ContentBrand $brand, array $rawAnswers, ?string $current, User $actor): string
    {
        $answers = [];
        foreach ($rawAnswers as $key => $answer) {
            if (is_string($answer) && trim($answer) !== '') {
                $answers[$key] = trim($answer);
            }
        }
        if ($answers === []) {
            throw ContentActionRefused::because('Jawab minimal satu pertanyaan sebelum meminta bantuan AI.');
        }
        if (($reason = $this->budget->unavailableReason()) !== null) {
            throw ContentActionRefused::because($reason);
        }
        if (($until = $this->budget->circuitOpenUntil()) !== null) {
            throw ContentActionRefused::because('Provider AI sedang dijeda setelah beberapa kegagalan. Coba lagi setelah '.$until->timezone('Asia/Jakarta')->format('H:i').' WIB.');
        }

        $prompt = $this->prompts->build($brand, $answers, $current);
        $run = ContentBrandKnowledgeAssist::query()->create([
            'content_brand_id' => $brand->id,
            'requested_by_user_id' => $actor->id,
            'status' => GenerationStatus::Running,
            'provider' => $this->generator->provider(),
            'model' => $this->generator->model(),
            'prompt_version' => $prompt->version,
            'started_at' => now(),
        ]);
        ($this->audit)('content.brand_knowledge_assist_requested', 'content_brand', $brand->public_id, $actor, null, [
            'run' => $run->public_id,
            'provider' => $run->provider,
            'model' => $run->model,
            'prompt_version' => $run->prompt_version,
        ]);

        $started = hrtime(true);
        try {
            $response = $this->generator->generate($prompt);
            $draft = GeneratedBrandKnowledge::parse($response->content, $response->inputTokens, $response->outputTokens);
        } catch (CopyProviderFailed $failure) {
            $this->recordFailure($run, $brand, $failure, (int) ((hrtime(true) - $started) / 1_000_000));
            throw ContentActionRefused::because($failure->getMessage());
        }

        $latency = (int) ((hrtime(true) - $started) / 1_000_000);
        $run->forceFill([
            'status' => GenerationStatus::Succeeded,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'cost_usd' => $this->budget->cost($response->inputTokens, $response->outputTokens),
            'latency_ms' => $latency,
            'provider_request_id' => $response->requestId === null ? null : mb_substr($response->requestId, 0, 120),
            'finished_at' => now(),
        ])->save();
        ($this->audit)('content.brand_knowledge_assist_succeeded', 'content_brand', $brand->public_id, $actor, null, [
            'run' => $run->public_id,
            'cost_usd' => (float) $run->cost_usd,
        ]);

        return $draft->sourceText;
    }

    private function recordFailure(ContentBrandKnowledgeAssist $run, ContentBrand $brand, CopyProviderFailed $failure, int $latency): void
    {
        $run->forceFill([
            'status' => GenerationStatus::Failed,
            'input_tokens' => $failure->inputTokens,
            'output_tokens' => $failure->outputTokens,
            'cost_usd' => $this->budget->cost($failure->inputTokens, $failure->outputTokens),
            'latency_ms' => $latency,
            'error_code' => $failure->reason,
            'error_message' => mb_substr($failure->getMessage(), 0, 500),
            'finished_at' => now(),
        ])->save();
        ($this->audit)('content.brand_knowledge_assist_failed', 'content_brand', $brand->public_id, $run->requester, null, [
            'run' => $run->public_id,
            'error' => $failure->reason,
        ], $failure->getMessage());
    }
}
