<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Support\Api\ApiResponse;
use App\Support\DatabaseProbe;
use Illuminate\Http\JsonResponse;

final class HealthController
{
    public function __invoke(DatabaseProbe $database): JsonResponse
    {
        $available = $database->isAvailable();

        return ApiResponse::success([
            'status' => $available ? 'ok' : 'degraded',
            'checks' => ['application' => 'ok', 'database' => $available ? 'ok' : 'unavailable'],
        ], $available ? 200 : 503);
    }
}
