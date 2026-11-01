<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Brands\Enums\ApplicationCode;
use App\Domain\Packages\Models\Package;
use App\Http\Resources\Api\PackageResource;
use App\Support\Api\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PackageController
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['application_code' => ['nullable', Rule::enum(ApplicationCode::class)]]);
        $application = $validated['application_code'] ?? null;

        $packages = Package::query()
            ->with('application')
            ->where('is_active', true)
            ->when($application !== null, static fn (Builder $q) => $q->whereRelation('application', 'code', $application))
            ->orderBy('application_id')
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::success(PackageResource::collection($packages)->resolve($request));
    }
}
