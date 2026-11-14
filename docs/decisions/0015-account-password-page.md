# ADR-0015: A personal account page for the password; names change only in Pengguna

- Status: Accepted
- Date: 2026-11-14
- Decision owners: Prasetya Group (owner, 2026-11-14)
- Related: [ADR-0009](0009-single-access-users-username-login.md), [ADR-0014](0014-back-office-redesign.md), [`../back-office-user-management-plan.md`](../back-office-user-management-plan.md)

## Context

Pengaturan mixes two different things: the signed-in person's own account (a Profil form for their name, and a password form) and the platform's configuration (gateway, application, system activity). The owner wants the two separated, and does not want people to edit their own profile: a name changes only through user management. A person may still change their own password.

ADR-0009 keeps one access level. There is no admin role, and this decision does not add one.

## Decision

1. **The Profil form is removed.** `PUT /settings/profile` and `SettingsController::updateProfile` are deleted. A person's name is changed only in **Pengguna** (`PUT /users/{user}`), the same place where accounts are created and passwords are set for others.
2. **The own-password form moves to a personal page.** `GET /account/password` shows **Ganti Kata Sandi**; `PUT /account/password` changes it with the same rules as before (current password required, `Password::defaults()`, session regenerated, `user.password_changed` audited). `PUT /settings/password` is deleted.
3. **The account menu opens it.** The header's account menu lists **Ganti Kata Sandi** and **Keluar**. Pengaturan stays in the sidebar and holds only platform configuration and Aktivitas Sistem.
4. **History stays readable.** Existing `user.profile_updated` audit entries keep their label in system activity; nothing new writes that action.

## Alternatives considered

- **Add an Admin role so only admins reach Pengguna.** Rejected by the owner for now: it would reverse ADR-0009 and add a role column, policies, navigation filtering, and tests.
- **Keep the password form inside Pengaturan.** Rejected by the owner: personal account actions and platform configuration stay apart.

## Consequences

- Because every user can open Pengguna (ADR-0009), anyone can still rename any account, their own included, there. This decision removes the self-service profile form; it is not an access control.
- Two routes are removed and two are added; no data or schema changes.
- Tests that used `/settings/profile` and `/settings/password` move to `/account/password`, and a test asserts the profile route is gone.
