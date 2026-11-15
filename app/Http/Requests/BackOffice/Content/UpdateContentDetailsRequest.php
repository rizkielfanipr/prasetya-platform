<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use App\Domain\Content\Support\ContentPlan;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateContentDetailsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Pillars are labels (ADR-0017): one can be picked, or named to add it.
        return [...ContentDetailsRules::rules(), 'new_pillar' => ['nullable', 'string', 'max:80', 'prohibits:pillar']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [...ContentDetailsRules::messages(), 'new_pillar.prohibits' => 'Pilih pilar yang ada atau tulis pilar baru, tidak keduanya.'];
    }

    public function plan(): ContentPlan
    {
        return ContentDetailsRules::plan($this);
    }
}
