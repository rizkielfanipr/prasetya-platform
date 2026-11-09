# Testing strategy

Tests never touch real Duitku credentials or the network. Backend and browser tests need a local MySQL with the databases `prasetya_platform_testing` and `prasetya_platform_e2e`.

| Layer | Location | Proves | Command |
|---|---|---|---|
| Unit | `tests/Unit` | Money, transitions, idempotency decisions, redaction, invoice format | `vendor/bin/pest tests/Unit` |
| Architecture | `tests/Architecture` | Domain has no HTTP or gateway dependency, only the adapters call out, Content Studio never touches orders, payments, packages, or HTTP clients, strict types | `vendor/bin/pest tests/Architecture` |
| Feature | `tests/Feature` | API envelope, validation, idempotency, CORS, rate limits, username sign-in, one access level, routes behind `auth`, user management, transaction workspace views, reconciliation, catalogue availability, Content Studio workflow, Brand Knowledge (one free-text editor, immutable versions, deterministic chunks, unchanged-save refusal, audit, keyword retrieval into `copy-v4`, no customer/order data), its AI interview (validation, provider failure safety (no spending limit by owner decision), usage audit, strict/fenced/wrapped JSON and structured-Markdown parsing, editable proposal without an automatic save), legacy structured brand validation, AI copy with faked HTTP, revision loop, research security, posting workflow, and insight summaries | `vendor/bin/pest tests/Feature` |
| Integration | `tests/Integration` | Duitku adapter with faked HTTP, webhook verification, status confirmation, duplicates, ordering, rollback, uniqueness, the Satamu order-to-paid flow | `vendor/bin/pest tests/Integration` |
| Component | `tests/js` | Formatters, badges, table, filters, pagination, dialog, timelines, content week view, status actions, editor, revision comparison, copy feedback, rule findings, pillar composition, legacy structured-rule compatibility, AI panel with polling | `npm test` |
| Browser | `tests/e2e` | Critical flows, sidebar navigation, one free-text Brand Knowledge document saved and reloaded, the Brand Knowledge AI interview handing an editable proposal to the textarea, legacy campaign redirect, campaign planning on Calendar, an AI draft with the fake provider, revision loop, web research, Siap posting, insight, console stays clean, axe WCAG A and AA, keyboard, responsive overflow | `npm run test:e2e` |

## Fixtures

- `legacyContentFixture()` (`tests/Support/content.php`) builds one item in each ADR-0011 status: frozen revisions through the real actions, then the legacy status, approval rows, and `approved_revision_id` written as the retired actions wrote them. `StatusMigrationRehearsalTest` runs `LegacyStatusBackfill` forward and backward on it inside the test transaction: mapping, idempotency, untouched evidence, rollback without invented approvals, damaged data reported, and `content:status-audit` changing nothing. The schema half (`ready_revision_id`) is rehearsed with `migrate:rollback --step=1` and `migrate` on the disposable e2e database, never in Pest (MySQL DDL would commit the test transaction).

- `DuitkuAdapterTest` uses the response shape from Duitku's documented sample, and checks signatures against `hash_hmac` over the documented strings.
- `DemoDataSeeder` builds data through the real actions and the real signed callback path, so the demo cannot show a state the application cannot reach. Refused outside local and testing. Addresses are `@example.test`.
- Packages start inactive except the approved Webworks ones. Tests that order Satamu call `activateSatamuPackages()`, which goes through `SetPackageAvailability` like an operator. `satamuOrderPayload()` is the documented Satamu request.
- `useDuitkuSandbox()`, `duitkuCallback()`, and `sendDuitkuCallback()` in `tests/Support/helpers.php` run the real Duitku adapter and verifier against faked HTTP, with callbacks signed as Duitku signs them.
- `ContentDemoSeeder` builds one week of Prasetya Webworks posts in every status through the real actions (one operator, `demo`; no approvals), plus illustrative brand rules, three pillars, and one fact marked *Data contoh*. Production gets none of these. Content tests reach states through the actions in `tests/Support/content.php` (`draftItem`, `readyItem`, `publishedItem`), never by setting a status; only the legacy fixture writes rows directly.
- `Database\Seeders\Support\DemoImage` writes valid PNGs without GD, because CI and cPanel do not install it. JPEG and WebP tests use minimal headers, which is all `finfo` and `getimagesize` read.
- Feature tests call `Http::preventStrayRequests()`, and `phpunit.xml` forces `CONTENT_TEXT_PROVIDER=none` with empty keys, so a developer's real AI settings in `.env` are never used by tests.
- AI tests never reach a network: `Http::fake()` for the OpenAI-compatible adapter and `Sleep::fake()` for backoff. Browser tests use `CONTENT_TEXT_PROVIDER=fake` and `CONTENT_SEARCH_PROVIDER=fake` (`.env.e2e`, and the CI browser step). `resources/content-eval/copy-v1.json` holds the provider evaluation scenarios run by `php artisan content:eval-copy`.
- `phpunit.xml` sets `memory_limit=512M`: the whole suite plus the architecture scan peaks around 140 MB, over PHP's 128 MB default.
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
