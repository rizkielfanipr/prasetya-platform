# Testing strategy

Tests never touch real Duitku credentials or the network. Backend and browser tests need a local MySQL with the databases `prasetya_platform_testing` and `prasetya_platform_e2e`.

| Layer | Location | Proves | Command |
|---|---|---|---|
| Unit | `tests/Unit` | Money, transitions, idempotency decisions, redaction, invoice format | `vendor/bin/pest tests/Unit` |
| Architecture | `tests/Architecture` | Domain has no HTTP or gateway dependency, only the adapters call out, strict types | `vendor/bin/pest tests/Architecture` |
| Feature | `tests/Feature` | API envelope, validation, idempotency, CORS, rate limits, username sign-in, one access level, routes behind `auth`, user management, transaction workspace views, compatibility redirects with preserved filters, reconciliation, catalogue availability, Satamu filters and detail | `vendor/bin/pest tests/Feature` |
| Integration | `tests/Integration` | Duitku adapter with faked HTTP, webhook verification, status confirmation, duplicates, ordering, rollback, uniqueness, the Satamu order-to-paid flow | `vendor/bin/pest tests/Integration` |
| Component | `tests/js` | Formatters, badges, table, filters, pagination, dialog, timelines | `npm test` |
| Browser | `tests/e2e` | Critical flows, four-item navigation, legacy redirects, console stays clean, axe WCAG A and AA, keyboard, responsive overflow | `npm run test:e2e` |

## Fixtures

- `DuitkuAdapterTest` uses the response shape from Duitku's documented sample, and checks signatures against `hash_hmac` over the documented strings.
- `DemoDataSeeder` builds data through the real actions and the real signed callback path, so the demo cannot show a state the application cannot reach. Refused outside local and testing. Addresses are `@example.test`.
- Packages start inactive except the approved Webworks ones. Tests that order Satamu call `activateSatamuPackages()`, which goes through `SetPackageAvailability` like an operator. `satamuOrderPayload()` is the documented Satamu request.
- `useDuitkuSandbox()`, `duitkuCallback()`, and `sendDuitkuCallback()` in `tests/Support/helpers.php` run the real Duitku adapter and verifier against faked HTTP, with callbacks signed as Duitku signs them.
- Browser tests fail on any console error, warning, or uncaught exception unless a test declares the expected HTTP failure.

## Satamu coverage

`tests/Integration/SatamuPaymentFlowTest.php`, `tests/Feature/CatalogueAvailabilityTest.php`, and `tests/Feature/BackOffice/SatamuTransactionsTest.php` cover the checklist in [`../satamu-payment-activation-plan.md`](../satamu-payment-activation-plan.md):

- All three Satamu packages priced by the catalogue; price, currency, and status fields from the browser have no effect.
- Satamu order with a Webworks package, Webworks order with a Satamu package, inactive and unknown packages are refused.
- Only `theme_code`, `event_type`, `event_date` (`Y-m-d`), and `notes` are stored.
- Order and checkout replay, checkout in progress, and a retry with a new key after a failed attempt.
- `SAT-YYYYMM-NNNNNN` invoices and the `PAYMENT_RETURN_URL_SATAMU` return page.
- Forged signature, unknown invoice, amount mismatch, unconfirmed success, gateway outage, failure result, delayed confirmation, duplicate delivery; a paid order has exactly one `PAID` and one `CONFIRMED` transition.
- A browser return with Duitku's query parameters changes nothing.
- Deactivating a package still lets an issued attempt settle.
- CORS for the approved Satamu origin, look-alikes, and an explicitly configured development origin.

## Gates

`.github/workflows/ci.yml` runs all of the above against MySQL 8, including a fresh migration and seed, and the production build.

## What these tests cannot show

- Compatibility with the live Duitku service: see the checklist in [`../api/duitku-contract.md`](../api/duitku-contract.md).
- True concurrency. Locks and unique constraints exist and uniqueness is tested, but no test races two real connections.
- Screen reader behaviour. Semantics are checked by axe and keyboard flows by Playwright; nobody has used the product with a screen reader yet.
