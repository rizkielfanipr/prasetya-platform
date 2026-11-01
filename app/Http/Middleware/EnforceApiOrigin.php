<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Api\ApiErrorCode;
use App\Support\Api\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** CORS only stops browsers reading a response. This stops a disallowed site's browser request from creating anything. */
final class EnforceApiOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if ($origin !== null && ! in_array($origin, (array) config('cors.allowed_origins'), true)) {
            return ApiResponse::error(ApiErrorCode::OriginNotAllowed, 'Origin tidak diizinkan.', 403);
        }

        return $next($request);
    }
}
