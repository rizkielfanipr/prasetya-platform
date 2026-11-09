# Content Studio and Hermes runtime

| Field | Value |
|---|---|
| Status | Draft: principles only. Nothing is installed. |
| Decision | [ADR-0011](../decisions/0011-content-studio-authority-boundary.md) |

Content Studio works without any AI service. This runbook will hold the steps to run Hermes, the AI Content Strategist, once phase 5 starts. Exact image names, versions, and commands will be taken from Hermes' own documentation at that time and tested before they are written here.

## Rules that already apply

- Nothing is installed (Docker Desktop, Hermes, global tools), no provider key is created, and no server is changed without the owner's explicit approval.
- **Local (macOS):** Docker Desktop as the engine, operated with the Docker CLI and Compose. Data lives in a volume or a directory outside the repository (for example `~/.hermes`). Setup is interactive: no key in a command line, `compose.yaml`, or this repository. Start without a permanent schedule and without risky tools. Development keys and dummy data only.
- **Staging and production:** Docker Engine, CLI, and Compose plugin on a host that is not the cPanel server (which has no Docker). The host is an open decision.
  - A pinned, tested image version or digest; never a floating `latest`.
  - Only Hermes' data volume is mounted: never Laravel's source, `.env`, logs, backups, SSH keys, or the Docker socket.
  - Non-root, `no-new-privileges`, resource limits, restart policy, health check, and log rotation.
  - The gateway or dashboard is not reachable from the internet.
  - Egress is limited to the AI provider, the research provider, and Content Studio's internal endpoint.
  - Its secrets are separate from Laravel's, with the least permissions.
- **Fallback:** to stop Hermes, stop the container and revoke its service token. Content Studio keeps working manually. Keep the volume for investigation until a retention decision allows deleting it.

## To be written in phase 5

Install and first run (local), VPS setup, backup and restore of the volume, credential rotation, image upgrade and rollback, provider outage, and budget incident.
