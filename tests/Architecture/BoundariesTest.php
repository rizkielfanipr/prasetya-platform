<?php

declare(strict_types=1);

// ADR-0001 required control: prohibited dependencies are checked, not just reviewed.
arch('the domain does not depend on the Duitku adapter or HTTP')
    ->expect('App\Domain')
    ->not->toUse(['App\Integrations', 'App\Http']);

arch('only the Duitku adapter makes outbound HTTP calls')
    ->expect('Illuminate\Support\Facades\Http')
    ->toOnlyBeUsedIn('App\Integrations\Duitku');

arch('models do not call gateways')
    ->expect('App\Domain\Payments\Contracts\PaymentGateway')
    ->not->toBeUsedIn(['App\Domain\Payments\Models', 'App\Domain\Orders\Models']);

arch('content studio never touches orders, payments, packages, or gateways (ADR-0011)')
    ->expect('App\Domain\Content')
    ->not->toUse(['App\Domain\Orders', 'App\Domain\Payments', 'App\Domain\Packages', 'App\Domain\Customers', 'Illuminate\Support\Facades\Http']);

arch('controllers do not open transactions')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('strict types everywhere')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debug helpers')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
