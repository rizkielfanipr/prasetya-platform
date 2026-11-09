<?php

declare(strict_types=1);

use Database\Seeders\ApplicationSeeder;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

require_once __DIR__.'/Support/helpers.php';
require_once __DIR__.'/Support/content.php';

pest()->extend(TestCase::class)->in('Unit', 'Architecture');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        // No test may reach a real service: every outbound call must be faked.
        Http::preventStrayRequests();
        $this->seed([ApplicationSeeder::class, PackageSeeder::class]);
    })
    ->in('Feature', 'Integration');
