<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use App\Domain\Content\Models\ContentBrandProfile;
use Illuminate\Foundation\Http\FormRequest;

/** A brand's rules. Lists are short phrases, at most 50 of 200 characters each; every save needs a change note. */
final class UpdateBrandRulesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'audience' => ['nullable', 'string', 'max:2000'],
            'voice' => ['nullable', 'string', 'max:2000'],
            'visual_guidance' => ['nullable', 'string', 'max:2000'],
            'caption_min_length' => ['nullable', 'integer', 'min:0', 'max:2200'],
            'caption_max_length' => ['nullable', 'integer', 'min:1', 'max:2200'],
            'hashtag_min' => ['nullable', 'integer', 'min:0', 'max:30'],
            'hashtag_max' => ['nullable', 'integer', 'min:0', 'max:30'],
            'topic_repeat_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'change_note' => ['required', 'string', 'max:500'],
        ];
        foreach (ContentBrandProfile::LISTS as $list) {
            $max = in_array($list, ['approved_examples', 'rejected_examples'], true) ? 2200 : 200;
            $rules[$list] = ['present', 'array', 'max:50'];
            $rules["{$list}.*"] = ['nullable', 'string', "max:{$max}"];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['change_note.required' => 'Tulis alasan perubahan aturan.', '*.max' => 'Terlalu panjang atau terlalu banyak.'];
    }
}
