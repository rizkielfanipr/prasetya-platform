<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use App\Domain\Content\Support\PostingQueue;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A person's statement that the post is live: the actual posting time is required, the Instagram URL optional. */
final class MarkContentPublishedRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'revision' => ['required', 'string', 'size:26'],
            'published_at' => ['required', 'date_format:'.BusinessTime::LOCAL_FORMAT],
            'url' => ['nullable', 'string', 'max:500', static function (string $attribute, mixed $value, Closure $fail): void {
                $parts = is_string($value) ? parse_url($value) : false;
                $host = is_array($parts) ? strtolower($parts['host'] ?? '') : '';
                if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ! in_array($host, ['instagram.com', 'www.instagram.com'], true) || isset($parts['user'])) {
                    $fail('Isi URL posting Instagram yang diawali https://www.instagram.com/.');
                }
            }],
            'note' => ['nullable', 'string', 'max:1000'],
            'checklist' => ['required', 'array', 'size:'.count(PostingQueue::CHECKLIST)],
            'checklist.*' => ['string', 'distinct', Rule::in(PostingQueue::CHECKLIST)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['checklist.required' => 'Centang semua langkah checklist posting.', 'checklist.size' => 'Centang semua langkah checklist posting.', 'published_at.required' => 'Isi waktu konten benar-benar diposting.', 'published_at.date_format' => 'Isi tanggal dan jam konten diposting.'];
    }

    /** @return list<string> */
    public function checklist(): array
    {
        $checklist = $this->validated('checklist');

        return is_array($checklist) ? array_values(array_filter($checklist, 'is_string')) : [];
    }

    public function publishedAt(): CarbonImmutable
    {
        return BusinessTime::parseLocal((string) $this->validated('published_at'));
    }
}
