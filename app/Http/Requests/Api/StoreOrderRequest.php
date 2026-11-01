<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Domain\Brands\Enums\ApplicationCode;
use App\Domain\Orders\Data\CreateOrderData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Price, amount, and total are not rules, so a value sent by a client never reaches the domain. */
final class StoreOrderRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'application_code' => ['required', Rule::enum(ApplicationCode::class)],
            'package_code' => ['required', 'string', 'max:64'],
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.email' => ['required', 'email:rfc', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()-]{6,32}$/'],
            'details' => ['nullable', 'array'],
            'details.notes' => ['nullable', 'string', 'max:2000'],
            // Webworks
            'details.template_code' => ['nullable', 'string', 'max:64'],
            'details.business_name' => ['nullable', 'string', 'max:255'],
            'details.domain_preference' => ['nullable', 'string', 'max:255'],
            // Satamu
            'details.theme_code' => ['nullable', 'string', 'max:64'],
            'details.event_type' => ['nullable', 'string', 'max:64'],
            'details.event_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function toData(): CreateOrderData
    {
        $application = ApplicationCode::from((string) $this->validated('application_code'));
        $details = (array) $this->validated('details', []);
        $allowed = $application === ApplicationCode::Webworks
            ? ['template_code', 'business_name', 'domain_preference', 'notes']
            : ['theme_code', 'event_type', 'event_date', 'notes'];

        return new CreateOrderData(
            application: $application,
            packageCode: (string) $this->validated('package_code'),
            customerName: (string) $this->validated('customer.name'),
            customerEmail: strtolower((string) $this->validated('customer.email')),
            customerPhone: $this->validated('customer.phone'),
            details: array_intersect_key($details, array_flip($allowed)),
        );
    }

    /** Fingerprint of the validated input, so a reused key with a different request is detected. */
    public function fingerprint(): string
    {
        $validated = $this->validated();
        ksort($validated);

        return hash('sha256', (string) json_encode($validated));
    }
}
