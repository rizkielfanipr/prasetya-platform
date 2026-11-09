<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use Illuminate\Foundation\Http\FormRequest;

final class AssistBrandKnowledgeRequest extends FormRequest
{
    private const array QUESTIONS = ['overview', 'audience', 'offers', 'differentiators', 'voice', 'visual', 'rules', 'operations'];

    protected $errorBag = 'brandKnowledgeAssistant';

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array:'.implode(',', self::QUESTIONS)],
            'answers.*' => ['nullable', 'string', 'max:5000'],
            'current_source_text' => ['nullable', 'string', 'max:50000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'answers.required' => 'Jawab minimal satu pertanyaan.',
            'answers.*.max' => 'Setiap jawaban maksimal 5.000 karakter.',
            'current_source_text.max' => 'Brand Knowledge saat ini maksimal 50.000 karakter.',
        ];
    }
}
