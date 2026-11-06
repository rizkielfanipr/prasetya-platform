# Testing strategy

Tests never touch real Duitku credentials or the network. Backend and browser tests need a local MySQL with the databases `prasetya_platform_testing` and `prasetya_platform_e2e`.

| Layer | Location | Proves | Command |
|---|---|---|---|
| Unit | `tests/Unit` | Money, transitions, idempotency decisions, redaction, invoice format | `vendor/bin/pest tests/Unit` |
| Architecture | `tests/Architecture` | Domain has no HTTP or gateway dependency, only the adapters call out, strict types | `vendor/bin/pest tests/Architecture` |
| Feature | `tests/Feature` | API envelope, validation, idempotency, CORS, rate limits, auth, role matrix, screens, reconciliation | `vendor/bin/pest tests/Feature` |
| Integration | `tests/Integration` | Duitku adapter with faked HTTP, webhook verification, status confirmation, duplicates, ordering, rollback, uniqueness | `vendor/bin/pest tests/Integration` |
| Component | `tests/js` | Formatters, badges, table, filters, pagination, dialog, timelines | `npm test` |
| Browser | `tests/e2e` | Critical flows, console stays clean, axe WCAG A and AA, keyboard, responsive overflow | `npm run test:e2e` |

## Fixtures

- `DuitkuAdapterTest` uses the response shape from Duitku's documented sample, and checks signatures against `hash_hmac` over the documented strings.
- `DemoDataSeeder` builds data through the real actions and the real signed callback path, so the demo cannot show a state the application cannot reach. Refused outside local and testing. Addresses are `@example.test`.
- Browser tests fail on any console error, warning, or uncaught exception unless a test declares the expected HTTP failure.

## Gates

`.github/workflows/ci.yml` runs all of the above against MySQL 8, including a fresh migration and seed, and the production build.

## What these tests cannot show

- Compatibility with the live Duitku service: see the checklist in [`../api/duitku-contract.md`](../api/duitku-contract.md).
- True concurrency. Locks and unique constraints exist and uniqueness is tested, but no test races two real connections.
- Screen reader behaviour. Semantics are checked by axe and keyboard flows by Playwright; nobody has used the product with a screen reader yet.
