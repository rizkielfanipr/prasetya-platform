<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice\Content;

use App\Domain\Content\Models\ContentInsightSnapshot;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** Metrics read from Instagram's insights for one post, at one time (business time). */
final class RecordInsightRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = ['measured_at' => ['required', 'date_format:'.BusinessTime::LOCAL_FORMAT], 'note' => ['nullable', 'string', 'max:500']];
        foreach (ContentInsightSnapshot::METRICS as $metric) {
            $rules[$metric] = [$metric === 'reach' ? 'required' : 'nullable', 'integer', 'min:0', 'max:1000000000'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['reach.required' => 'Jangkauan wajib diisi.', 'measured_at.required' => 'Isi kapan angka ini dibaca.', '*.min' => 'Angka tidak boleh negatif.'];
    }

    /** @return array<string, int|null> */
    public function metrics(): array
    {
        $values = [];
        foreach (ContentInsightSnapshot::METRICS as $metric) {
            $value = $this->validated($metric);
            $values[$metric] = $value === null ? null : (int) $value;
        }

        return $values;
    }

    public function measuredAt(): CarbonImmutable
    {
        return BusinessTime::parseLocal((string) $this->validated('measured_at'));
    }
}
