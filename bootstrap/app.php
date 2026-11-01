<?php

declare(strict_types=1);

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Api\ApiErrorCode;
use App\Support\Api\ApiException;
use App\Support\Api\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->api(append: [LogApiRequest::class]);
        $middleware->web(append: [HandleInertiaRequests::class, EnsureUserIsActive::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        $middleware->encryptCookies();
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([ApiException::class]);
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password']);

        // Public API: one envelope for every failure, and no internal detail in any of them.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof ApiException => ApiResponse::error($e->errorCode, $e->getMessage(), $e->status, $e->fields),
                $e instanceof ValidationException => ApiResponse::error(ApiErrorCode::ValidationFailed, 'Data yang dikirim tidak valid.', 422, $e->errors()),
                $e instanceof ThrottleRequestsException => ApiResponse::error(ApiErrorCode::RateLimited, 'Terlalu banyak permintaan.', 429)->withHeaders($e->getHeaders()),
                $e instanceof NotFoundHttpException => ApiResponse::error(ApiErrorCode::NotFound, 'Data tidak ditemukan.', 404),
                $e instanceof MethodNotAllowedHttpException => ApiResponse::error(ApiErrorCode::MethodNotAllowed, 'Metode tidak diizinkan.', 405),
                $e instanceof HttpExceptionInterface && $e->getStatusCode() < 500 => ApiResponse::error(ApiErrorCode::NotFound, 'Permintaan tidak dapat diproses.', $e->getStatusCode()),
                default => ApiResponse::error(ApiErrorCode::InternalError, 'Terjadi kesalahan pada server.', 500),
            };
        });

        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            return redirect()->route('login')->with('error', 'Sesi Anda berakhir. Silakan masuk kembali.');
        });

        // Back office: permission denied and not found are designed states, rendered inside the app.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();
            $rendersInApp = in_array($status, [403, 404], true)
                || (in_array($status, [500, 503], true) && ! config('app.debug'));

            if ($request->is('api/*') || ! $rendersInApp) {
                return $response;
            }

            return Inertia::render('errors/ErrorPage', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
