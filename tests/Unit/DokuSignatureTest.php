<?php

declare(strict_types=1);

use App\Integrations\Doku\DokuSignature;

// Expected values were computed independently with openssl, see docs/testing/strategy.md.
it('builds the documented component string with no trailing newline', function (): void {
    expect(DokuSignature::components('MCH-1', 'req-1', '2020-08-11T08:45:42Z', '/checkout/v1/payment', '{"a":1}'))
        ->toBe("Client-Id:MCH-1\nRequest-Id:req-1\nRequest-Timestamp:2020-08-11T08:45:42Z\nRequest-Target:/checkout/v1/payment\nDigest:".base64_encode(hash('sha256', '{"a":1}', true)));
});

it('omits Digest on GET, as DOKU documents', function (): void {
    expect(DokuSignature::components('MCH-1', 'req-1', 't', '/orders/v1/status/INV', null))
        ->not->toContain('Digest');
});

it('matches an independently computed vector', function (): void {
    expect(DokuSignature::digest('{"a":1}'))->toBe('AVq9f1zFei3ZS3WQ8ErYCEJzkF7jPsXOvq5iJ2qX+GI=')
        ->and(DokuSignature::sign('secret', 'MCH-1', 'req-1', '2020-08-11T08:45:42Z', '/checkout/v1/payment', '{"a":1}'))->toBe('HMACSHA256=fMn2wWtzRMj+vLOBMyuMX8I4zqEFLehCMX0XoARO5J8=');
});
