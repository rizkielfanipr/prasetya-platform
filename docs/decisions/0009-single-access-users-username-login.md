# ADR-0009: One access level, back-office user management, and username login

- Status: Proposed
- Date: 2026-11-07
- Decision owners: Prasetya Group
- Related: [`../back-office-user-management-plan.md`](../back-office-user-management-plan.md), [ADR-0002](0002-mvp-payment-monitoring-scope.md) (roles row), [`../security/threat-model.md`](../security/threat-model.md)

## Context

The first release has three roles (Super Admin, Finance, Operations) with seven permissions, enforced by policies, gates, and a filtered navigation ([ADR-0002](0002-mvp-payment-monitoring-scope.md)). Accounts can only be created on the command line (`platform:create-admin`), so adding a colleague needs SSH access. Sign-in uses email and password, and password recovery depends on email, but production has no mail transport (`MAIL_MAILER=log`).

The team is small and every member does every task. The owner wants anyone on the team to add a colleague from the back office, every account to have full access, and sign-in with a username and password only.

## Decision

1. **One access level.** Every active, signed-in user can use every back-office screen and action. Roles, permissions, policies, and role-based navigation are removed.
2. **Users are managed in the back office.** A new **Pengguna** module lets any user list and create users, change their names, activate or deactivate them, and set a new password for them. Users are never deleted, because audit entries, notes, and status histories reference them.
3. **Username and password sign-in.** `username` is unique, lower-case, 3–32 characters from `a–z`, `0–9`, `.`, `_`, and `-`, and cannot be changed after the account is created. Email is hidden and no longer used. The creator types the initial password; a forced change at first sign-in is left for later.
4. **No self-service password reset by email.** A user who forgets their password asks a colleague to set a new one in **Pengguna**. If nobody can sign in, an operator runs `php artisan platform:reset-password <username>` on the server.
5. **Safeguards that stay:**
   - only active users can sign in, and deactivation ends their sessions;
   - a user cannot deactivate themselves, and the last active user cannot be deactivated;
   - setting another user's password ends that user's sessions;
   - every user change is audited without passwords;
   - sign-in attempts are rate-limited, and errors are generic.
6. **Payment invariants are unchanged.** No screen or route may set a payment status; only `ApplyPaymentEvidence` produces `PAID`.

## Alternatives considered

- **Keep the three roles and add user management.** Rejected for now. Nobody needs restricted access, and role assignment would add screens and tests without a user who needs them.
- **Keep email sign-in.** Rejected. The owner prefers usernames, and email gives nothing extra while mail is not configured.
- **Email password reset with SMTP configured.** Deferred. Admin-set passwords cover a small team without a mail dependency.
- **Delete users.** Rejected. It would break audit and history references; deactivation removes access just as well.

## Consequences

- Any compromised account can see finance data, run gateway checks, and manage users. The mitigations are few accounts, the 12-character password policy, rate limiting, audit, and quick deactivation. Two-factor authentication is the natural next step if the team grows.
- Adding a colleague no longer needs SSH.
- Password recovery depends on another active user, or on the CLI.
- The roles row of ADR-0002 is superseded when this ADR is accepted. Role-matrix tests are replaced by tests for "every active user can use every screen" and "guests and inactive users cannot".
- A migration replaces `role` with `username` and backfills usernames for existing accounts. The runbook must tell operators how to find them.

## Validation

- Feature tests:
  - username sign-in;
  - rate limits and generic errors;
  - the forgotten-password routes are gone;
  - every route requires an active user;
  - user CRUD, the safeguards, session invalidation, and audit without secrets.
- A migration test for the username backfill with collisions.
- Browser tests: sign in with a username; create a user and sign in as them; deactivate them and confirm the session ends; axe and responsive checks on **Pengguna**.
