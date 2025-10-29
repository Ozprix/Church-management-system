# DevOps & Deployment Guide

This guide describes the baseline DevOps tooling for the Church Management SaaS platform.

## CI Pipelines

### Laravel API

- **api-ci.yml** – Runs PHPUnit suite against an ephemeral SQLite database for pull requests and pushes to `dev`.
- **api-container.yml** – Builds the production Docker image using the hardened Dockerfile and scans it with Trivy for high/critical vulnerabilities.

Both workflows are scoped to `apps/api/**` to keep CI fast. Extend them with additional jobs (lint, static analysis) as the codebase grows.

## Container Image

The production image lives in `apps/api/docker/Dockerfile` and features:

- Multi-stage build to install Composer dependencies separately from the runtime image.
- Alpine PHP-FPM base with only required extensions (`pdo_mysql`, `bcmath`, `zip`, `opcache`).
- Non-root `app` user (configurable via build args) and read/write storage directories pre-owned by that user.
- `app-entrypoint` script that warms Laravel caches before starting `php-fpm`.
- Opinionated OPCache settings shipped via `docker/php/conf.d/opcache.ini`.
- Built-in health check calling `php artisan octane:status` (fallback to `php -v`).

Build locally:
```bash
cd apps/api
docker build -f docker/Dockerfile -t church-api:local .
```

## Secrets Management

- Use SOPS + age for encrypting `.env` files stored under `infra/secrets/`.
- GitHub Actions decrypt secrets using the `SOPS_AGE_KEY` secret.
- Terraform should provision long-lived secrets in AWS Secrets Manager (or equivalent) and inject them at runtime.

Refer to `docs/secrets-management.md` for detailed instructions.

## Deployment Hooks

- Prior to deployment, run `php artisan migrate --force` and `php artisan rbac:sync --prune-roles --prune-features` to ensure tenancy metadata is current.
- Container entrypoint handles cache priming (`config`, `route`, `view`), but be prepared to clear caches when toggling feature flags.

## Scheduler Jobs

Run the Laravel scheduler (`php artisan schedule:work` or a cron invoking `schedule:run` every minute) on the API host. The schedule currently triggers:

- `members:run-saved-reports` daily at `06:00` for member analytics digests.
- `attendance:run-saved-reports` daily at `06:30` for the new attendance report emails/exports. Ensure this job uses the same worker environment (queue + notifications) as other tenant automation so recurring digests are delivered. When monitoring production, spot-check that:
  - CSV snapshots land on the configured disk (`storage/app/reports/attendance/<report-id>/…`) with fresh timestamps.
  - Email notifications are queued with `status=queued` via `php artisan tinker --execute="App\Models\Notification::where('channel','email')->latest()->take(5)->get();"`.
  - Queue workers are draining `SendNotificationJob` jobs (e.g., `php artisan queue:failed` remains empty and worker logs emit “Mock Mailgun email send” if running in mock mode).
- `attendance:cleanup-snapshots` daily at `05:30` to prune expired snapshot files. Use `php artisan attendance:cleanup-snapshots --dry-run` during ops reviews to see what will be deleted.
- Hourly reminder queues such as `finance:recurring-run`, `volunteers:send-followups`, and the existing visitor follow-up cadence.

Include the scheduler service alongside the queue worker in deployment manifests (e.g., Supervisor, systemd, or container sidecar) so these commands run continuously.

## Future Enhancements

- Add static analysis (PHPStan/Psalm) and security scanning (Larastan, Dependabot) to CI.
- Wire Terraform modules to publish images to ECR/GCR and deploy to ECS/Kubernetes with blue/green rollout.
- Introduce smoke tests that run inside the container image post-build.
