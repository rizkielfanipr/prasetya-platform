<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Logging\OperationLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $response = $next($request);

        // Route pattern, not the URL: public order identifiers and query strings stay out of the log.
        OperationLog::record('api.request', (string) $response->getStatusCode(), [
            'method' => $request->method(),
            'route' => $request->route()?->uri(),
            'application' => $request->input('application_code'),
        ], $startedAt);

        return $response;
    }
}
