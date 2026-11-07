<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The post's content and, optionally, a new image. Lengths follow Instagram's limits where it has one. The image's
 * type, ratio, and size are decided from its bytes by ContentImageInspector, not here.
 */
final class SaveContentRevisionRequest extends FormRequest
{
    public const int MAX_HASHTAGS = 30;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'revision' => ['required', 'string', 'size:26'],
            'objective' => ['nullable', 'string', 'max:1000'],
            'audience' => ['nullable', 'string', 'max:1000'],
            'brief' => ['nullable', 'string', 'max:5000'],
            'caption' => ['nullable', 'string', 'max:2200'],
            'hashtags' => ['nullable', 'string', 'max:1000', static function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && preg_match_all('/#[^\s#]+/u', $value) > self::MAX_HASHTAGS) {
                    $fail('Instagram menerima maksimal '.self::MAX_HASHTAGS.' hashtag.');
                }
            }],
            'alt_text' => ['nullable', 'string', 'max:1000'],
            'visual_direction' => ['nullable', 'string', 'max:3000'],
            'image' => ['nullable', 'file', 'max:8192'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['image.max' => 'Ukuran gambar maksimal 8 MB.', 'image.uploaded' => 'Gambar gagal diunggah. Ukuran maksimal 8 MB.'];
    }
}
