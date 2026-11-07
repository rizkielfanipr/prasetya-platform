# Back-office user management and username sign-in plan

| Field | Value |
|---|---|
| Status | Implemented on branch `feat/user-management`, awaiting the owner's acceptance |
| Owner | Prasetya Group |
| Last updated | 2026-11-07 |
| Decision | [ADR-0009](decisions/0009-single-access-users-username-login.md) |
| Related | [ADR-0002](decisions/0002-mvp-payment-monitoring-scope.md), [`design.md`](design.md), [`security/threat-model.md`](security/threat-model.md), [`data-dictionary.md`](data-dictionary.md) |

## Objective

Let any back-office user add and manage colleagues from a new **Pengguna** module, give every account the same full access, and sign in with a username and password instead of an email.

## Progress (2026-11-07)

| Phase | Commit | State |
|---|---|---|
| 2 Username sign-in | `feat(platform): sign in with username` | Done |
| 3 One access level | `refactor(platform): remove roles and permissions` | Done |
| 4 Pengguna module | `feat(platform): manage users in the back office` | Done |
| 5 Docs and rollout | `docs: user management and username sign-in` | Docs done; rollout after merge |

Differences from the scope below, decided during implementation:

- The migration is split in two (`…_sign_in_with_username`, `…_remove_user_roles`), one per phase. Both ship in the same release.
- `platform:create-admin` lost `--role` in the username phase already, because `CreateUser` gives every new user full access.
- Row actions sit in one "Aksi" menu per row instead of three buttons, so the table fits at 1280 px. On phones the table scrolls horizontally with a sticky name column, like the transaction list, rather than turning into cards.
- A user cannot set their own password from Pengguna; they change it in Pengaturan with their current password. Otherwise the action would end their own session and skip that check.
- The last-active-user rule cannot be reached over HTTP (the only active user is always the actor, so the self rule answers first). It stays in `SetUserActive` as a guard against races and calls without an actor, and is tested there.

ADR-0009 becomes Accepted, and the roles row of ADR-0002 is marked superseded in the ADR index, once the owner accepts this implementation.

## Current state

| Area | Today | Code |
|---|---|---|
| Accounts | Created on the command line only | `routes/console.php` (`platform:create-admin`), `AdminUserSeeder` |
| Access | Three roles, seven permissions, four policies, two gates | `app/Domain/Auth/Enums/{Role,Permission}.php`, `app/Policies/*`, `AppServiceProvider` |
| Navigation | Items hidden by permission | `HandleInertiaRequests` (`auth.permissions`), `AppSidebar.vue`, `AppHeader.vue` |
| User list | Read-only table on Pengaturan, Super Admin only | `SettingsController::index`, `AdminPanels.vue` |
| Sign-in | Email and password, 5 attempts a minute per email and IP | `LoginRequest`, `LoginPage.vue` |
| Recovery | Email reset link, but production mail is `log` | `PasswordResetController`, `ForgotPasswordPage.vue`, `ResetPasswordPage.vue` |
| Profile | Name and email editable, password change | `SettingsController::updateProfile`, `ProfileForm.vue`, `PasswordForm.vue` |
| Deactivation | Ends the session on the next request | `EnsureUserIsActive` |

## Scope

### Data

New migration `…_username_login_and_single_access.php`:

1. Add `users.username` (`string(32)`), nullable at first.
2. Backfill existing users from the email local part:
   - lower-case it;
   - replace characters outside `[a-z0-9._-]` with `-`;
   - trim to 32 characters;
   - add `-2`, `-3`, … on collision.
3. Make `username` not null and unique.
4. Make `email` nullable. It is no longer used for sign-in and is hidden everywhere (not shown, not editable, not required); the column keeps existing data until a later cleanup.
5. Drop `users.role` and its index.
6. Drop the `password_reset_tokens` table.
7. `down()` re-adds `role`, with every existing user as `SUPER_ADMIN`, and recreates `password_reset_tokens`. Emails nulled in production cannot be restored, so roll back only from a backup.

Username rules, in one place (`App\Domain\Auth\Username`):

- 3–32 characters;
- pattern `^[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?$`;
- stored lower-case and trimmed;
- matched case-insensitively on sign-in;
- **immutable**: set once at creation and never changed afterwards, by the user or by anyone else.

### Sign-in

- `LoginRequest`:
  - takes `username` and `password`;
  - authenticates with `username` (normalised), `password`, and `is_active = true`;
  - rate-limits 5 attempts a minute per username and IP, plus 20 a minute per IP, to slow guessing across usernames;
  - answers with the generic message *Username atau kata sandi salah.*
- `LoginPage.vue`:
  - the label is **Username**, with `autocomplete="username"`;
  - the *Lupa kata sandi?* link is replaced by the hint *Lupa kata sandi? Hubungi admin lain.*
- Remove the `forgot-password` and `reset-password` routes, `PasswordResetController`, both pages, the `password-reset` rate limiter, and `ResetPassword::createUrlUsing`.
- Keep *Ingat saya*, session regeneration on sign-in, the `auth.login` and `auth.logout` audits, and `last_login_at`.

### Access

- Remove `Role`, `Permission`, `User::hasPermission`, the four policies, the `view-reconciliation` and `manage-settings` gates, and the `auth.permissions` Inertia prop.
- The `authorize()` methods in `GatewayCheckRequest` and `StoreAdminNoteRequest` return true. The `auth` and `EnsureUserIsActive` middleware remain the only gate.
- `TransactionController::show` always includes webhooks and audit entries.
- `SettingsController::index` always includes the gateway and application panels.
- The sidebar and header show every item. Remove `Role`, `Permission`, and `roleLabels` from `resources/js`.
- Add an architecture test that every web route except sign-in, the health check, and the dev fake checkout is behind `auth`.

### Pengguna module

Navigation order: Dashboard, Transaksi, Event Pembayaran, Rekonsiliasi, Audit Log, **Pengguna**, Pengaturan.

| Route | Purpose |
|---|---|
| `GET /users` | List with search (name or username) and status filter |
| `POST /users` | Create |
| `PUT /users/{user:public_id}` | Change name (the username cannot be changed) |
| `POST /users/{user:public_id}/deactivate`, `…/activate` | Change status |
| `PUT /users/{user:public_id}/password` | Set a new password for another user |

**Domain actions** in `app/Domain/Auth/Actions`, so controllers stay thin:

| Action | Rules |
|---|---|
| `CreateUser` | Name required (max 255); username unique and valid; password typed by the creator, following `Password::defaults()` (12 characters, letters and numbers) with confirmation |
| `UpdateUser` | Name only; any `username` in the request is ignored. The change is audited with before and after |
| `SetUserActive` | Refuses to deactivate the acting user or the last active user. Deactivation deletes the user's rows in `sessions` and clears `remember_token` |
| `ResetUserPassword` | Sets the password, deletes the user's sessions, and clears `remember_token`. A user changes their own password in Pengaturan, which asks for the current one |

**Audit actions:** `user.created`, `user.updated`, `user.activated`, `user.deactivated`, `user.password_reset`. They record the actor, the target public id, and changed field names and values, never passwords or hashes.

**Interface** (Vue, following [`design.md`](design.md) and the existing transaction list):

- **List.** A table with name, username, status badge (Aktif / Nonaktif), last sign-in, and created date. The acting user is marked *(Anda)*. Empty and no-results states. On phones the table becomes cards.
- **Create dialog.** Name, username, password, and confirmation, with visible labels and errors under each field. The username hint explains the allowed characters and that it cannot be changed later. Password fields have a show/hide control.
- **Edit dialog.** Name only; the username is shown read-only.
- **Deactivate.** A confirmation dialog states that the user is signed out immediately. The action is disabled for the acting user and the last active user, with the reason shown.
- **Set password.** A dialog with password and confirmation. A success message reminds the admin to share it privately.
- **Pengaturan:**
  - the user table on Pengaturan is removed, and the gateway and application panels stay;
  - the profile form edits the name only, and the username is shown read-only;
  - the password form is unchanged.

### Command line and seeders

| Command or seeder | Change |
|---|---|
| `platform:create-admin` | Becomes `platform:create-admin {username} {name}`, with no `--role`; prompts for the password |
| `platform:reset-password` | New: `platform:reset-password {username}`. Prompts for the new password, ends that user's sessions, and is audited as `Sistem` |
| `platform:list-users` | New: prints username, name, status, and last sign-in, so operators can see the backfilled usernames after the migration |
| `AdminUserSeeder` | Reads `ADMIN_USERNAME` instead of `ADMIN_EMAIL` (update `.env.example`) |
| `DemoDataSeeder` | Demo users become `demo` and `demo2`, both full access |

## Out of scope

- Two-factor authentication.
- Email delivery and self-service password reset.
- Roles or per-screen permissions.
- Deleting users.
- Bulk import.
- Forcing a password change on first sign-in (later development).
- Changing a username after creation.
- A password generator.

## Delivery phases

Each phase is one PR that passes every gate in `AGENTS.md`.

1. **Decision.** Approve ADR-0009. The open questions were answered on 2026-11-07 (see the end of this document).
2. **Username sign-in** (`feat(platform): sign in with username`):
   - the migration steps for `username` and nullable `email`;
   - `Username`, `LoginRequest`, and the login page;
   - removal of password reset;
   - `platform:create-admin`, `platform:reset-password`, and `platform:list-users`;
   - seeders, tests, and docs.
3. **One access level** (`refactor(platform): remove roles and permissions`):
   - drop `role`;
   - remove the enums, policies, gates, the Inertia permissions prop, and the navigation filtering;
   - replace the role-matrix tests;
   - add the route architecture test.
4. **Pengguna module** (`feat(platform): manage users in the back office`):
   - the domain actions, routes, controller, requests, presenter, and Vue pages and dialogs;
   - the audit actions;
   - feature, component, and browser tests.
5. **Docs and rollout** (`docs: user management and username sign-in`): sync every document listed below and run the rollout.

Phases 2 and 3 can ship together if review time is short; phase 4 depends on both.

## Required tests

**Sign-in (feature):**
- A valid username signs in, case-insensitively and with surrounding spaces.
- A wrong password and an unknown username give the same generic error.
- An inactive user cannot sign in.
- Both rate limits apply.
- The old `email` field does not authenticate.
- The `forgot-password` and `reset-password` routes answer 404.

**Access (feature and architecture):**
- Any active user reaches every screen and action that used to be role-limited: reconciliation, Cek status, audit log, payment events, settings panels, and transaction evidence.
- Guests are redirected to sign-in.
- A deactivated user is signed out on the next request.
- Every web route except the allowed ones uses `auth`.

**Users (feature):**
- Create, with every validation rule (uniqueness ignores case).
- Update the name; a `username` sent with an update is ignored and the username stays the same.
- Deactivating yourself is refused.
- Deactivating the last active user is refused.
- Deactivation and a password reset delete the user's sessions and remember token.
- Users are addressed by public id, and numeric ids answer 404.
- Every audit action appears without password data.
- No response or page exposes a user's email.

**Migration and command line:**
- The backfill produces valid, unique usernames for colliding and unusual emails.
- `platform:create-admin`, `platform:reset-password`, and `platform:list-users` work, refuse invalid input, and write audit entries.

**Component and browser:**
- The user form validation messages render.
- Sign-in uses a username.
- Create a user, sign out, and sign in as that user.
- Deactivate a signed-in user in a second browser context; their next request lands on sign-in.
- Set a password and sign in with it.
- **Pengguna** passes axe and has no horizontal scroll at 360, 768, and 1280 px.
- `permissions.spec.ts` is replaced.

## Documents to update

- `data-dictionary.md`: the `users` row (`username`, nullable `email`, no `role`, no `password_reset_tokens`).
- `design.md`:
  - the login form (Username, the recovery hint);
  - the navigation list (add Pengguna);
  - drop "Items a role cannot use are not shown";
  - the Pengguna page pattern.
- `security/threat-model.md`:
  - password guessing (per username and per IP);
  - privilege escalation (no roles, by design);
  - new risk: a compromised account has full access (mitigations from ADR-0009).
- `testing/strategy.md`: role matrix → access and user tests.
- `runbooks/deployment.md`:
  - first-time setup uses `platform:create-admin <username> "<name>"`;
  - after this release, run `platform:list-users` to find the backfilled usernames;
  - recovery with `platform:reset-password`.
- `runbooks/incident-response.md`: the compromised-account steps use **Pengguna** (deactivate, set password) instead of database edits.
- `implementation-plan.md` (M8 roles line), the ADR index, and `docs/README.md`.
- `.env.example`: `ADMIN_USERNAME` instead of `ADMIN_EMAIL`.

## Rollout

1. Back up the database.
2. Deploy through CI. The migration backfills usernames.
3. On the server, run `php artisan platform:list-users`, and give each person their username privately.
4. Sign in with a username. Check **Pengguna**, then create and deactivate a test user.
5. Remove `ADMIN_EMAIL` and `ADMIN_PASSWORD` from the production `.env` if they are still there.

## Rollback

Redeploy the previous release and restore the database backup taken in step 1. `migrate:rollback` restores the columns but not the original roles or any nulled emails, so prefer a forward fix unless the release is unusable.

## Acceptance criteria

- A signed-in user can create a colleague, who can then sign in with a username and password and reach every screen.
- No role, permission, or email sign-in remains in code, UI, or tests.
- Nobody can deactivate themselves or the last active user.
- Deactivating a user or setting their password ends their sessions immediately.
- Every user change is in the audit log, without passwords.
- Every gate in `AGENTS.md` passes.
- The documents above are updated in the same change.

## Decisions on the open questions

Answered by the owner on 2026-11-07.

| Question | Decision |
|---|---|
| Email | Hidden now: column nullable, not shown, not editable, not used. Dropped in a later cleanup |
| First sign-in | No forced password change in this release; later development |
| Renaming | A username cannot be changed after creation |
| Initial password | Typed by the user who creates the account |
