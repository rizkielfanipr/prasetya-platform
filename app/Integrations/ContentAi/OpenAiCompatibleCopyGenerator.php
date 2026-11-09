<?php

declare(strict_types=1);

namespace App\Integrations\ContentAi;

use App\Domain\Content\Ai\CopyModels;
use App\Domain\Content\Ai\CopyPrompt;
use App\Domain\Content\Ai\CopyProviderFailed;
use App\Domain\Content\Ai\CopyResponse;
use App\Domain\Content\Contracts\CopyGenerator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Copy through any OpenAI-compatible Chat Completions API: DeepSeek (https://api.deepseek.com), OpenAI, OpenRouter,
 * or a compatible gateway. JSON mode is requested and the answer is still validated by GeneratedCopy.
 *
 * Never logs or returns the key, the Authorization header, or a raw response body.
 */
final class OpenAiCompatibleCopyGenerator implements CopyGenerator
{
    public function __construct(private readonly CopyModels $models) {}

    public function provider(): string
    {
        return 'openai_compatible';
    }

    public function model(): ?string
    {
        return $this->models->selected()?->name;
    }

    public function unavailableReason(): ?string
    {
        $baseUrl = (string) config('content.ai.copy.base_url');
        $missing = array_keys(array_filter([
            'CONTENT_TEXT_BASE_URL' => ! str_starts_with($baseUrl, 'https://'),
            'CONTENT_TEXT_API_KEY' => blank(config('content.ai.copy.api_key')),
            'CONTENT_TEXT_MODEL atau CONTENT_TEXT_MODELS' => $this->model() === null,
        ]));

        return $missing === [] ? null : 'Provider AI belum lengkap dikonfigurasi: '.implode(', ', $missing).'.';
    }

    public function generate(CopyPrompt $prompt): CopyResponse
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('content.ai.copy.base_url'), '/'))
                ->withToken((string) config('content.ai.copy.api_key'))
                ->acceptJson()
                ->timeout((int) config('content.ai.copy.timeout_seconds'))
                ->post('/chat/completions', [
                    'model' => $this->model(),
                    'messages' => [
                        ['role' => 'system', 'content' => $prompt->system],
                        ['role' => 'user', 'content' => $prompt->user],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'max_tokens' => $prompt->maxOutputTokens,
                    'temperature' => 0.7,
                ]);
        } catch (ConnectionException $e) {
            $timedOut = str_contains(strtolower($e->getMessage()), 'timed out');

            throw new CopyProviderFailed($timedOut ? 'timeout' : 'unreachable', $timedOut ? 'Provider AI tidak menjawab tepat waktu.' : 'Provider AI tidak dapat dihubungi.', true);
        }

        $status = $response->status();
        if ($status === 401 || $status === 403) {
            throw new CopyProviderFailed('auth', 'API key provider AI ditolak. Periksa CONTENT_TEXT_API_KEY dan saldo akun.', false);
        }
        if ($status === 402) {
            throw new CopyProviderFailed('auth', 'Saldo akun provider AI tidak cukup.', false);
        }
        if ($status === 429) {
            throw new CopyProviderFailed('rate_limited', 'Provider AI membatasi permintaan (429).', true);
        }
        if ($status >= 500) {
            throw new CopyProviderFailed('server_error', "Provider AI sedang bermasalah ({$status}).", true);
        }
        if (! $response->successful()) {
            throw new CopyProviderFailed('bad_request', "Provider AI menolak permintaan ({$status}). Periksa CONTENT_TEXT_MODEL.", false);
        }

        $input = (int) $response->json('usage.prompt_tokens', 0);
        $output = (int) $response->json('usage.completion_tokens', 0);
        $finish = $response->json('choices.0.finish_reason');
        $content = $response->json('choices.0.message.content');
        if ($finish === 'content_filter' || is_string($response->json('choices.0.message.refusal'))) {
            throw new CopyProviderFailed('refused', 'Provider AI menolak membuat konten ini.', false, $input, $output);
        }
        if ($finish === 'length') {
            throw CopyProviderFailed::invalidOutput('Jawaban AI terpotong karena batas panjang.', $input, $output);
        }
        if (! is_string($content) || trim($content) === '') {
            throw CopyProviderFailed::invalidOutput('Jawaban AI kosong.', $input, $output);
        }
        $id = $response->json('id');

        return new CopyResponse($content, $input, $output, is_string($id) ? $id : null);
    }
}
