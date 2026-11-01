<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Domain\Packages\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Package */
final class PackageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'application_code' => $this->application->code->value,
            'name' => $this->name,
            'description' => $this->description,
            'price' => ['amount' => $this->price_amount, 'currency' => $this->currency],
        ];
    }
}
