<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Route;

/*
 * One access level (ADR-0009) leaves the auth middleware as the only gate in front of the back office, so every web
 * route must use it except the few that are public by design.
 */
it('puts every back-office route behind auth', function (): void {
    $public = ['/', 'login', 'dev/fake-checkout/{payment}', '_inertia/devtools/entries', '_inertia/devtools/entries/{id}'];

    $unprotected = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => in_array('web', $route->gatherMiddleware(), true))
        ->reject(fn (Route $route): bool => in_array('auth', $route->gatherMiddleware(), true))
        ->map(fn (Route $route): string => $route->uri())
        ->reject(fn (string $uri): bool => in_array($uri, $public, true))
        ->values()
        ->all();

    expect($unprotected)->toBe([]);
});

it('signs out deactivated users on every authenticated route', function (): void {
    app(HttpKernel::class); // Registers the middleware groups with the router.
    $router = app('router');

    $missing = collect($router->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => in_array('auth', $route->gatherMiddleware(), true))
        ->reject(fn (Route $route): bool => in_array(EnsureUserIsActive::class, $router->gatherRouteMiddleware($route), true))
        ->map(fn (Route $route): string => $route->uri())
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
