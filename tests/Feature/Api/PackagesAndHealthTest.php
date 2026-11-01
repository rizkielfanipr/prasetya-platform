<?php

declare(strict_types=1);

use App\Domain\Packages\Models\Package;

it('reports health in the envelope', function (): void {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.checks.database', 'ok')
        ->assertJsonStructure(['success', 'data', 'meta', 'requestId'])
        ->assertHeader('X-Request-Id');
});

it('lists only active packages, filterable by brand', function (): void {
    Package::query()->where('code', 'PW-COMPANY-PROFILE')->update(['is_active' => false]);

    $codes = collect($this->getJson('/api/v1/packages?application_code=WEBWORKS')->assertOk()->json('data'))->pluck('code');

    expect($codes)->toContain('PW-LANDING-STARTER')->not->toContain('PW-COMPANY-PROFILE')->not->toContain('SAT-SEDERHANA');
});

it('exposes no internal ids on packages', function (): void {
    $package = $this->getJson('/api/v1/packages')->json('data.0');

    expect($package)->toHaveKeys(['code', 'application_code', 'name', 'price'])->not->toHaveKey('id')->not->toHaveKey('application_id');
});

it('uses the error envelope for validation and unknown routes', function (): void {
    $this->getJson('/api/v1/packages?application_code=NOPE')
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['error' => ['code', 'message', 'fields' => ['application_code']], 'requestId']);

    $this->getJson('/api/v1/nope')->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
    $this->getJson('/api/v1/orders/01HZZZZZZZZZZZZZZZZZZZZZZZ')->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
    $this->deleteJson('/api/v1/health')->assertStatus(405)->assertJsonPath('error.code', 'METHOD_NOT_ALLOWED');
});
