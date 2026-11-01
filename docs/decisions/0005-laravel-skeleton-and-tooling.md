# ADR-0005: Plain Laravel skeleton with explicit tooling

- Status: Proposed
- Date: 2026-11-01
- Decision owners: Prasetya Group
- Related: [`0001-modular-monolith.md`](0001-modular-monolith.md)

## Context

The plan says to scaffold the Laravel Vue/Inertia starter. The tagged `laravel/vue-starter-kit` available during scaffolding (v1.0.2) targets Laravel 12, Tailwind 3, and the older `radix-vue`, and brings registration, appearance switching, and account deletion that this internal tool must not have.

## Decision

- Start from `laravel/laravel` 13 and add Inertia 3, Vue 3, TypeScript, Tailwind 4, and shadcn-vue directly.
- Authentication is a small set of first-party controllers using Laravel's session guard and password broker. No public registration.
- Client routes are a typed constant in `resources/js/lib/routes.ts`, with no Ziggy or Wayfinder.
- shadcn-vue sources come from its official registry, new-york-v4 style. The CLI's install step fails under the current npm's script policy, so files were written from the registry JSON and `@/lib/utils` substituted for its `cn` import.
- Pest for backend tests, Larastan level 6 for static analysis, MySQL in every environment that runs tests, because locking and uniqueness are part of what is being tested.
- Laravel Boost, which the skeleton's generated `AGENTS.md` asks for, is not installed. It was not requested and is not needed to run the project.

## Alternatives considered

- **Use the starter kit `dev-main`.** Rejected: an untagged branch as a foundation, and it still needs the same removals.
- **SQLite for tests.** Rejected: does not exercise `lockForUpdate` or MySQL date arithmetic.

## Consequences

- Password reset and login are this project's code to maintain, covered by feature tests.
- Adding a shadcn-vue component uses the same registry route until the CLI works.

## Validation

A clean copy of the tracked files installs, migrates, tests, and builds with the commands in `README.md`.
