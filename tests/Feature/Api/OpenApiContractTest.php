<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/** Paths and methods from openapi.yaml, read without a YAML dependency: path keys are the only 2-space-indented keys starting with /. */
function documentedOperations(): array
{
    $operations = [];
    $path = null;

    foreach (file(base_path('docs/api/openapi.yaml'), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('#^  (/[^:]*):$#', $line, $match) === 1) {
            $path = $match[1];
        } elseif (preg_match('/^[a-z]/', $line) === 1) {
            $path = null;
        } elseif ($path !== null && preg_match('/^    (get|post|put|patch|delete):$/', $line, $match) === 1) {
            $operations[] = strtoupper($match[1]).' '.preg_replace('/\{[^}]+\}/', '{}', $path);
        }
    }

    sort($operations);

    return $operations;
}

it('documents exactly the routes that exist', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/'))
        ->map(fn ($route) => $route->methods()[0].' /'.preg_replace('/\{[^}]+\}/', '{}', substr($route->uri(), strlen('api/v1/'))))
        ->sort()
        ->values()
        ->all();

    expect(documentedOperations())->toBe($routes);
});
