# Threat model

Status: Proposed. Must be reviewed before production payments are enabled.

**Assets:** correctness of payment state, gateway keys, customer contact data, admin accounts, audit integrity.

**Trust boundaries:** browser to public API (untrusted), gateway to webhook (untrusted until verified), admin browser to back office (authenticated), platform to gateway (TLS, signed).

| Threat | Control | Evidence | Residual risk |
|---|---|---|---|
| Forged notification marks an order paid | HMAC over the raw body, Client-Id match, constant-time compare, empty secret rejected | `WebhookProcessingTest` | Secret compromise defeats it: rotate per runbook |
| Forged Duitku callback: the signature does not cover `resultCode` and is fixed per order | Server-to-server check decides status and amount before `ApplyPaymentEvidence`, source address allow-list | `DuitkuCallbackTest` | Check outage delays `PAID`. The address check is weak until `trustProxies('*')` is narrowed |
| Replay of a captured notification | Content-based duplicate key, `PAID` final, no side effect on repeat | Duplicate and replay tests | Replaying a first-seen `EXPIRED` for an open payment is possible only with a valid signature |
| Tampered price from the browser | Price only from catalogue, ignored in input, snapshot on order | `OrderApiTest` | |
| Notification with the wrong amount | Rejected, state unchanged, shown in reconciliation | Mismatch tests | |
| Browser return treated as paid | No return route exists; status only from evidence | Route test | Consumer sites must follow the API guide |
| Admin marks a payment paid | No such route. Gateway check goes through the evidence path | `AuthorizationTest` | Direct database access |
| Duplicate orders or checkouts | Idempotency keys, row lock, open attempt reuse, unique constraints | Feature tests | Not exercised under real concurrency |
| Order enumeration | ULID references, no contact data in public reads | `OrderApiTest` | A leaked reference exposes invoice, package, and status, and can start a checkout, which pays for that order only |
| Abuse creating orders | Rate limit per IP, origin allowlist | Tests | **No bot protection.** Origin can be forged outside a browser, so scripted order spam is limited only by rate limits. Consider CAPTCHA at M7 |
| Spam filling `webhook_events` | 120 per minute per IP, 64 KB cap, unverified bodies not stored | Tests | Rows still accumulate during an attack: no retention job yet |
| Rate limits behind a proxy | `trustProxies('*')` so client IP is read from forwarded headers | | **Must be narrowed to the real proxy at deployment.** With `*`, a directly reachable app lets a client spoof its IP and evade limits |
| Stolen admin session | Encrypted session, HttpOnly, SameSite Lax, Secure in production, regeneration on login | `AuthTest` | No 2FA. Recommended before many users |
| Password guessing | 5 attempts per minute per username and IP plus 20 per minute per IP, the same generic error for unknown usernames and wrong passwords, 12-character minimum | `AuthTest` | |
| Privilege escalation | Not applicable by design: one access level ([ADR-0009](../decisions/0009-single-access-users-username-login.md)). Every back-office route requires `auth` and an active user | `AccessTest`, `tests/Architecture/RoutesTest.php` | |
| Compromised account | Any active account has full access, including finance data, gateway checks, and user management. Mitigations: few accounts, 12-character passwords, rate limits, audit log, immediate deactivation that ends sessions | `AuthTest`, user management tests | No 2FA. Recommended before the team grows |
| CSRF | Laravel token on all web mutations; webhook and API are outside the web group | Framework | |
| XSS | Vue escaping, `v-html` banned by lint, production CSP | ESLint | CSP keeps `style-src 'unsafe-inline'` for attribute styles |
| Secrets in logs or pages | Redacting log processor, `SensitiveParameter`, config never shared, sanitised storage | Logging and settings tests | Only key-name based: a secret inside a free-text message would pass |
| Fake gateway reaching production | Refuses to boot when `APP_ENV=production` | Test | |
| Dummy Satamu prices sold | Created inactive in every environment; re-seeding never changes availability; activation only through the audited `platform:package-activate` command with a reason and confirmation | Test | Must be replaced before enabling |
| Audit tampering | Append-only models | Test | Not enforced in the database. Consider revoking UPDATE and DELETE for the app's MySQL user |
| Content posted by mistake | ADR-0012 (owner decision): one person drafts and records the post, no second reviewer. Ready needs complete content and no brand-rule error; editing Ready content creates a new revision and returns it to Draft; publishing needs the current revision to be Ready and a human posting time; AI and schedulers never change these statuses; every step is audited. The retired approval routes refuse and change nothing | `ContentWorkflowTest`, `ContentRulesTest`, `ContentAiTest` | No four-eyes check: a mistake is caught only by the creator, the rule check, and the audit trail |
| Reading another item's evidence by guessing an id (ADR-0013) | The research, history, and activity endpoints are GET only, behind `auth` and the active-user check like every back-office route, resolve the item by ULID public id (numeric ids and unknown ids are 404), and query only that item's sources, revisions, runs, and audit | `ContentDetailWorkspaceTest` | Any active user may read any item, by design (ADR-0009) |
| Malicious or oversized image upload | MIME from the bytes (JPEG, PNG, WebP), size and pixel limits read from the header before any decode, exact 4:5 ratio, private disk, path by checksum, download only behind sign-in | Upload tests | Images are not re-encoded, so metadata (EXIF) stays in the file |
| AI output treated as fact or approval | AI copy is always a new draft revision of a Draft item; it never marks content Ready, Published, or Archived, and a run finishing after the item left Draft is discarded. The answer is parsed as untrusted JSON (exact fields, lengths, control characters removed) and then checked by the deterministic brand rules: a price no approved fact states blocks Ready. The prompt tells the model DATA is material, not instructions | `ContentAiTest`, `ContentRulesTest`, `content:eval-copy` scenarios (including an injected brief) | A plausible but wrong sentence without numbers still needs the creator's check |
| AI provider key leak or runaway cost | Key only in the server environment, never in the database, browser, logs, audit, prompts, or error messages; one run at a time per item; circuit breaker after repeated provider failures; bounded output tokens, attempts, and timeout; dedicated queue. No spending limit in the application by owner decision (2026-11-08) | `ContentAiTest` (key never in responses, audit, or run errors; circuit breaker) | **Cost is only bounded by the provider's own spending limit**: set one there. Many users or a stuck loop of manual requests can still spend until that limit |
| Content data sent to a third-party AI | Only brand content is sent: rules, approved facts, plan, and the revision's text. No customer, order, or payment data | `ContentAiTest` (customer and order data absent from the request) | The provider's own retention applies; do not put secrets in brand rules or facts |
| SSRF through research (phase 4) | Only http/https on ports 80 and 443, no credentials; private, loopback, link-local, carrier-grade NAT, IPv4-mapped, unique-local, metadata, and internal names refused from the URL; the page reader checks every resolved address, pins the connection to it, re-checks each redirect, reads HTML only within time and size limits; search results with such URLs are dropped | `ResearchTest` (literal, DNS, mixed answers, redirect, ports, schemes) | DNS for the title reader is resolved by PHP; a resolver that lies is out of scope |
| Prompt injection through research sources | Source text is stored cleaned and short, shown as plain text, linked with `noopener noreferrer nofollow`, and given to AI only as background marked untrusted; facts, prices, and claims still come only from approved facts and the rule check | `ResearchTest`, `content:eval-copy` | A misleading but harmless sentence still needs the creator's check |
| AI or agent output treated as fact or approval (agent phases) | No Hermes tool can approve, publish, delete, or change facts; tool allowlists and limits enforced by Laravel | ADR-0011 | Not built yet |
| Hermes compromise (later phase) | Separate container without database credentials, Laravel `.env`, SSH keys, or Docker socket; service token per run; egress limited | ADR-0011; manifest review | Host not decided |
| Vulnerable dependencies | `npm audit` and `composer audit` | Run manually | Add to CI schedule |

## Before production

1. Sandbox checklist complete.
2. `trustProxies` narrowed.
3. Secrets generated and stored on the server only; `.env` unreadable from the web root.
4. Decide on bot protection and 2FA.
5. Retention policy for customer data and webhook rows.
6. Backup restore proven.
