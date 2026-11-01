<?php

declare(strict_types=1);

namespace App\Support\Api;

use App\Support\RequestContext;
use Illuminate\Http\JsonResponse;

/** The one place that defines the public API envelope. */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>|list<mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public static function success(array $data, int $status = 200, array $meta = []): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data' => $data,
            'meta' => (object) $meta,
            'requestId' => app(RequestContext::class)->requestId(),
        ], $status);
    }

    /** @param array<string, list<string>> $fields */
    public static function error(ApiErrorCode $code, string $message, int $status, array $fields = []): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'data' => null,
            'error' => [
                'code' => $code->value,
                'message' => $message,
                'fields' => (object) $fields,
            ],
            'requestId' => app(RequestContext::class)->requestId(),
        ], $status);
    }
}
