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
        return ContentDetailsRules::rules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ContentDetailsRules::messages();
    }

    public function plan(): ContentPlan
    {
        return ContentDetailsRules::plan($this);
    }
}
