# Church Management SaaS Platform

Comprehensive multi-tenant church management system blueprint covering membership, finance, events, communications, volunteer coordination, and analytics. The repository now includes the initial monorepo scaffolding so the Laravel API, Next.js PWA, and shared packages can be generated consistently across environments.

## Highlights
- Multi-tenant SaaS with strict row-level isolation enforced by `tenant_id`.
- Modular Laravel backend + Next.js PWA frontend using Tailwind CSS.
- Shared workspace packages for UI components, utilities, and generated API contracts.
- Security-first posture with RBAC, 2FA, encryption, auditing, compliance considerations.
- Ready for integrations (Stripe, PayPal, Twilio/Vonage, Mailgun/SendGrid) and scalable on cloud infrastructure.

## Repository Layout
```
root/
├─ apps/
│  ├─ api/                 # Laravel service (generated via bootstrap script)
│  └─ web/                 # Next.js PWA (generated via bootstrap script)
├─ packages/
│  ├─ ui/                  # Shared React component library scaffold
│  ├─ utils/               # Shared TypeScript utility helpers scaffold
│  └─ contracts/           # OpenAPI schemas & API client scaffold
├─ infra/
│  ├─ terraform/           # Infrastructure as code modules (network, RDS, Redis, S3)
│  └─ cicd/                # CI/CD pipelines and container assets
├─ docs/                   # Architecture, database design, roadmap, ADRs
└─ tools/
   ├─ scripts/             # Bootstrap helpers for api/web
   └─ seeders/             # Placeholder for demo data tooling
```

## Prerequisites
- Node.js 18+ with `corepack` enabled (for pnpm).
- Docker Desktop (for Laravel Sail bootstrap via Composer container).
- Internet access when running bootstrap scripts (downloads dependencies/images).

## Getting Started
```bash
corepack enable pnpm              # once per machine
make bootstrap-all                # generates Laravel + Next.js apps
# or run targets individually:
make bootstrap-api
make bootstrap-web
```

After scaffolding:
- `cd apps/api && cp .env.example .env && php artisan key:generate` (once Sail containers are up).
- `cd apps/web && cp .env.local.example .env.local && pnpm dev` to start the PWA.

Workspace scripts:
```bash
pnpm dev        # currently proxies to pnpm --filter web dev
pnpm lint       # run lint tasks once configured in sub-packages
pnpm test       # run workspace tests (placeholders for now)
```

## Shared Packages
- `@church/ui` exposes reusable React components configured for Tailwind.
- `@church/utils` houses cross-application helpers (formatting, hooks, validation).
- `@church/contracts` will contain generated API clients / DTOs from OpenAPI specs.

Each package ships with placeholder build scripts (`pnpm --filter <pkg> build`) and README guidance for next actions.

## Next Steps
1. Run the bootstrap scripts to install Laravel and Next.js locally.
2. Implement tenancy core (tenant discovery middleware, scoped ORM base) in `apps/api`.
3. Set up authentication (Laravel Sanctum + Next.js session handling).
4. Expand shared packages with real components/utilities as features are built.
5. Follow the roadmap in `docs/roadmap.md` to deliver domain modules incrementally.
6. Maintain ADRs for significant architectural decisions under `docs/adr/`.

Refer to documentation in `docs/` for detailed architecture, database schema, and implementation timeline guidance.

