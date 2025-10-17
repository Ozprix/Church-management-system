# Developer Setup Guide

This guide explains how to bring up the monorepo scaffolding for the church management SaaS platform.

## 1. Tooling Requirements
- **Node.js 18+** with `corepack` enabled (required for `pnpm`). You may need elevated permissions when enabling corepack because it creates symlinks in your global `bin` directory.
- **Docker Desktop** (used by Laravel Sail bootstrap image).
- **Git** for version control operations.

## 2. Install PNPM via Corepack
```bash
corepack enable pnpm
```

If your environment restricts access to `/usr/local/bin`, run the command with elevated privileges or install pnpm manually and ensure it is on your `PATH` before executing the bootstrap scripts.

## 3. Bootstrap Services
Generate both applications:
```bash
make bootstrap-all
```
Or individually:
```bash
make bootstrap-api   # Laravel backend via laravelsail/php82-composer
make bootstrap-web   # Next.js PWA via create-next-app
```

The scripts live under `tools/scripts/`, automatically remove placeholder files, and include safety checks to prevent overwriting non-empty directories.
For a pre-seeded demo tenant run:
```bash
tools/scripts/bootstrap-demo.sh
```

## 4. Post-Bootstrap Steps
### Laravel API (`apps/api`)
1. `cd apps/api`
2. Copy `.env.example` to `.env` and run `php artisan key:generate` inside the Sail container once it is installed.
3. Run `php artisan sail:install --services=mysql,redis,meilisearch,mailpit` and `./vendor/bin/sail up -d` to start local services.
4. Run pending migrations once tenancy scaffolding is added.

### Next.js PWA (`apps/web`)
1. `cd apps/web`
2. Copy `.env.local.example` to `.env.local`
3. Set `NEXT_PUBLIC_API_URL` to point at the Laravel API (`http://localhost/api` when using Sail) and optionally `NEXT_PUBLIC_TENANT_ID` for a default tenant slug during local development.
4. `pnpm dev` to start the development server.
5. Visit `/onboarding` for the interactive setup checklist and `/docs/api` for generated API documentation.

## 5. Shared Packages
- Located under `packages/*` with TypeScript build configs, ESLint rules, and Vitest suites prepared.
- Build using `pnpm --filter @church/<pkg> build` once dependencies are in place.
- Path aliases (`@ui/*`, `@utils/*`, `@contracts/*`) are configured in `tsconfig.base.json`.

## 6. Workspace Commands
```bash
pnpm dev        # currently proxies to Next.js dev (adjust as services grow)
pnpm lint       # runs lint targets defined in each workspace package
pnpm test       # runs Vitest suites package-by-package
pnpm test:unit  # runs Vitest via workspace config (vitest.workspace.ts)
```

## 7. Tenant CLI Helpers
Tenant-aware Artisan commands let you run maintenance tasks without hand-rolling tenancy bootstrapping. Use either local PHP (`php artisan …`) or Sail (`./vendor/bin/sail artisan …`) depending on your setup—start Sail with `./vendor/bin/sail up -d` before invoking Artisan inside the containers.

- `tenant:run {tenant} <command …>` executes any Artisan command for a single tenant (ID, UUID, or slug). Example (local PHP):  
  `php artisan tenant:run example queue:work --once`
- `tenant:seed {tenant} [--class=DemoSeeder] [--database=foo]` ensures the tenant context is set while running seeders.
- `tenant:run-batch <command …>` targets many tenants at once with filters such as `--plan`, `--status`, `--tenant`, `--except`, or the shortcut `--only-active`. Extra ergonomics include `--chunk`, `--delay`, `--pretend`, `--stop-on-failure`, interactive confirmation via `--confirm` (pair with `--yes` for non-interactive automation), and machine-readable summaries with `--format=json` (includes any identifiers skipped by filters).

### Sail examples
```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan tenant:run example cache:clear
./vendor/bin/sail artisan tenant:seed example --class=VolunteerSeeder
./vendor/bin/sail artisan tenant:run-batch cache:clear --plan=standard --pretend
./vendor/bin/sail artisan tenant:run-batch queue:restart --only-active --confirm
./vendor/bin/sail artisan tenant:run-batch reports:generate --format=json --tenant=example
```

If you need to pass additional Artisan flags, append them after the command (`tenant:run example queue:restart --force`). When using Sail, keep the `sail up` step separate—passing command options to `sail up` produces “unknown flag” errors.

## 8. RBAC Toolkit
- `php artisan rbac:sync [--tenant=<id|uuid|slug>] [--prune-roles] [--prune-features] [--prune-permissions]` keeps global permissions, tenant roles, and feature toggles aligned with `config/permissions.php`. Pair with `tenant:run-batch` for bulk execution once multi-tenant automation is needed.
- API endpoints (auth + `rbac.view` permission required):
  - `GET /api/v1/rbac/roles` returns tenant roles, assigned permissions, default status, and user counts.
  - `GET /api/v1/rbac/permissions` lists permission metadata grouped by module alongside current feature enablement.
- Seeders automatically call the RBAC sync workflow, so new environments and demo tenants include a consistent registry out of the box.

### Member Imports
- `POST /api/v1/members/bulk-import` supports JSON payloads (max 50 records per request) for synchronous creation; requests are throttled (`10/min`) to keep load predictable.
- For larger batches, upload a CSV via `POST /api/v1/member-imports` (fields: `first_name`, `last_name`, optional `membership_status`, `email`). The upload stores a `member_import` record, queues an async job (`imports` queue), and returns a stub resource with status `pending`.
- Poll `GET /api/v1/member-imports/{id}` to track completion; the job records processed/failed counts and per-row errors once finished. Files are stored under `storage/app/member-imports/{tenant_id}`.
- Bulk deletes run through `POST /api/v1/members/bulk-delete` (UUID list max 200) and are throttled similarly.
- Custom rate-limiters (`member-import-upload`, `member-bulk-operations`) emit structured logs and a `ThrottleLimitExceeded` event when caps are hit—wire those into your monitoring stack for alerting.
- Audit timeline endpoint: `GET /api/v1/members/{uuid}/audits` returns paginated change history (actions, actor, payload), powering the member detail activity feed.
- Family analytics: `GET /api/v1/families/analytics` + `/families/analytics/export` surfaces household metrics; finance analytics mirror this at `/api/v1/finance/analytics` + `/finance/analytics/export`.
- Front-end expectations: set `NEXT_PUBLIC_API_BASE_URL` (Laravel domain) and `NEXT_PUBLIC_TENANT_ID` (slug/UUID) so client fetches send Sanctum cookies with the correct `X-Tenant-ID` header. Dashboards live at `/members/analytics`, `/families/analytics`, and `/finance/analytics`.
- For local auth, create `.env.local` inside `apps/web` with:
  ```env
  NEXT_PUBLIC_API_BASE_URL=http://localhost:8080
  NEXT_PUBLIC_TENANT_ID=example
  ```
  Ensure Sanctum cookie domain/settings match your local host (update Laravel `.env` for `SESSION_DOMAIN` / `SANCTUM_STATEFUL_DOMAINS`). Start the API (`sail up`) and sign in via `/login`, then navigate to `/members`, `/families/analytics`, or `/finance/analytics` to verify end-to-end cookies.
  The default seeded admin user is `admin@example.com` with password `password` (see `DatabaseSeeder`).

## 9. Next Actions
- Install dependencies (`pnpm install`) after corepack/pnpm is enabled and network access is available.
- Populate Laravel tenancy middleware and Next.js application shells following `docs/architecture.md`.
- Keep generated contracts in sync by updating `packages/contracts/openapi/church.json` and re-running the generator script.

## 10. Two-Factor Authentication Workflow
- Login requests (`POST /api/v1/auth/login`) now issue a single active Sanctum token per user. When 2FA is enabled, include either `code` (TOTP) or `recovery_code`.
- Enable 2FA: `POST /api/v1/auth/two-factor/setup` (returns secret + recovery codes) followed by `POST /api/v1/auth/two-factor/confirm` with a valid TOTP code.
- Regenerate recovery codes: `POST /api/v1/auth/two-factor/recovery-codes` with a current TOTP code.
- Disable 2FA: `DELETE /api/v1/auth/two-factor` with either a code or recovery code; this revokes all active tokens.
- For operators: demo environments can seed a `DEMO_API_TOKEN`, now stored as a Sanctum personal access token named `demo-token`.
- Administrative reset: `POST /api/v1/auth/two-factor/admin-reset` (requires `users.manage_security`) clears a user’s configuration, revokes tokens, and queues an email notification.
