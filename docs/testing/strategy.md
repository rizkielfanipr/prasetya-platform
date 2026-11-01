# Testing strategy

Tests never touch real DOKU credentials or the network. Backend and browser tests need a local MySQL with the databases `prasetya_platform_testing` and `prasetya_platform_e2e`.

| Layer | Location | Proves | Command |
|---|---|---|---|
| Unit | `tests/Unit` | Money, transitions, signature vector, idempotency decisions, redaction, invoice format | `vendor/bin/pest tests/Unit` |
| Architecture | `tests/Architecture` | Domain has no HTTP or DOKU dependency, only the adapter calls out, strict types | `vendor/bin/pest tests/Architecture` |
| Feature | `tests/Feature` | API envelope, validation, idempotency, CORS, rate limits, auth, role matrix, screens, reconciliation | `vendor/bin/pest tests/Feature` |
| Integration | `tests/Integration` | DOKU adapter with faked HTTP, webhook verification, duplicates, ordering, rollback, uniqueness | `vendor/bin/pest tests/Integration` |
| Component | `tests/js` | Formatters, badges, table, filters, pagination, dialog, timelines | `npm test` |
| Browser | `tests/e2e` | Critical flows, console stays clean, axe WCAG A and AA, keyboard, responsive overflow | `npm run test:e2e` |

## Fixtures

- `DokuSignatureTest` uses a vector computed independently with `openssl`, so it does not just compare the code with itself:

  ```sh
  DIG=$(printf '{"a":1}' | openssl dgst -sha256 -binary | openssl base64)
  printf 'Client-Id:MCH-1\nRequest-Id:req-1\nRequest-Timestamp:2020-08-11T08:45:42Z\nRequest-Target:/checkout/v1/payment\nDigest:%s' "$DIG" \
    | openssl dgst -sha256 -hmac secret -binary | openssl base64
  ```

- `DokuAdapterTest` uses the response shape from DOKU's documented sample.
- `DemoDataSeeder` builds data through the real actions and the real signed webhook path, so the demo cannot show a state the application cannot reach. Refused outside local and testing. Addresses are `@example.test`.
- Browser tests fail on any console error, warning, or uncaught exception unless a test declares the expected HTTP failure.

## Gates

`.github/workflows/ci.yml` runs all of the above against MySQL 8, including a fresh migration and seed, and the production build.

## What these tests cannot show

- Compatibility with the live DOKU service: see the checklist in [`../api/doku-contract.md`](../api/doku-contract.md).
- True concurrency. Locks and unique constraints exist and uniqueness is tested, but no test races two real connections.
- Screen reader behaviour. Semantics are checked by axe and keyboard flows by Playwright; nobody has used the product with a screen reader yet.
