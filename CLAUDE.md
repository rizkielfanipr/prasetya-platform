# Prasetya Platform

Read `docs/README.md`, then the document for your area. `docs/` is the source of truth; a material change needs an ADR in `docs/decisions/` first.

## Rules that are easy to break

- Only `ApplyPaymentEvidence` may move a payment to `PAID`. No route or screen may set a payment status directly.
- All status changes go through `TransitionPaymentStatus` and `TransitionOrderStatus`.
- Price comes from the package catalogue. Never read it from a request.
- Duitku specifics stay in `app/Integrations/Duitku`. Do not invent Duitku fields: check `docs/api/duitku-contract.md` and Duitku's documentation.
- Never store or log signatures, raw webhook bodies, secrets, or more personal data than needed.
- No network call inside a database transaction.
- Controllers stay thin. Rules live in `app/Domain`. `tests/Architecture` enforces the boundaries.
- Vue: semantic tokens only, no raw colours. No `any`. Labels, statuses, routes, and formatters have one home in `resources/js/lib`.
- Any brand colour used as text or under white text needs the derived token: see ADR-0003.

## Before you finish

```sh
vendor/bin/pint && npm run format
vendor/bin/phpstan analyse --memory-limit=1G
npm run lint && npm run typecheck && npm test
vendor/bin/pest
npm run build && npm run test:e2e
```

Update `docs/` in the same change when behaviour, routes, or setup change. Commits use Conventional Commits.
