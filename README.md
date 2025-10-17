# 🏛️ Church Management SaaS Platform

A **comprehensive multi-tenant church management system** blueprint covering membership, finance, events, communications, volunteer coordination, and analytics.

This monorepo includes scaffolding so the **Laravel API**, **Next.js PWA**, and **shared packages** can be generated consistently across environments.

---

## ✨ Highlights

- 🧩 **Multi-tenant SaaS** with strict row-level isolation enforced by `tenant_id`
- ⚙️ **Modular architecture:** Laravel backend + Next.js PWA frontend (Tailwind CSS)
- 🧱 **Shared workspace packages** for UI components, utilities, and generated API contracts
- 🔒 **Security-first posture:** RBAC, 2FA, encryption, auditing, and compliance
- ☁️ **Cloud-ready design:** integrates with Stripe, PayPal, Twilio/Vonage, Mailgun/SendGrid

---

## 🗂️ Repository Layout

```text
root/
├─ apps/
│  ├─ api/        # Laravel service (generated via bootstrap script)
│  └─ web/        # Next.js PWA (generated via bootstrap script)
├─ packages/
│  ├─ ui/         # Shared React component library scaffold
│  ├─ utils/      # Shared TypeScript utility helpers scaffold
│  └─ contracts/  # OpenAPI schemas & API client scaffold
├─ infra/
│  ├─ terraform/  # Infrastructure as code modules (network, RDS, Redis, S3)
│  └─ cicd/       # CI/CD pipelines and container assets
├─ docs/          # Architecture, database design, roadmap, ADRs
└─ tools/
   ├─ scripts/    # Bootstrap helpers for api/web
   └─ seeders/    # Demo data tooling
```

---

## 🧰 Prerequisites

- **Node.js 18+** (with Corepack enabled for `pnpm`)
- **Docker Desktop** (for Laravel Sail bootstrap via Composer container)
- **Internet access** for downloading dependencies and images

---

## ⚡ Getting Started

```bash
# Enable pnpm (once per machine)
corepack enable pnpm

# Bootstrap full stack
make bootstrap-all

# Or run targets individually
make bootstrap-api
make bootstrap-web
```

After scaffolding:

```bash
# Laravel setup
cd apps/api
cp .env.example .env
php artisan key:generate

# Next.js setup
cd ../web
cp .env.local.example .env.local
pnpm dev
```

---

## 🧭 Workspace Scripts

| Command | Description |
| --- | --- |
| `pnpm dev` | Run development server (currently proxies to `apps/web`) |
| `pnpm lint` | Run linting tasks (once configured) |
| `pnpm test` | Execute workspace tests (placeholder for now) |

---

## 🧩 Shared Packages

| Package | Purpose |
| --- | --- |
| `@church/ui` | Reusable React components pre-configured for Tailwind |
| `@church/utils` | Cross-application helpers (formatting, hooks, validation) |
| `@church/contracts` | Generated API clients / DTOs from OpenAPI specs |

Each package includes placeholder build scripts (`pnpm --filter <pkg> build`) and internal README guidance.

---

## 🧱 Next Steps

1. Run the bootstrap scripts to install Laravel and Next.js locally.
2. Implement tenancy core: discovery middleware and scoped ORM base models.
3. Set up authentication: Laravel Sanctum for the API and session handling in Next.js.
4. Expand shared packages with production-ready components and utilities.
5. Follow `docs/roadmap.md` for incremental delivery of domain modules.
6. Maintain ADRs under `docs/adr/` for key architectural decisions.

---

## 🧭 Documentation

Check the `docs/` directory for:

- Architecture overview
- Database schema
- Implementation timeline
- Roadmap

---

## 🤝 Contributing

```bash
# Clone the repo & install dependencies

# Branch from dev
git checkout dev
git pull
git switch -c feature/<your-feature-name>

# Make your changes and commit
git add .
git commit -m "feat: describe your change"

# Push and open a Pull Request (feature → dev)
git push origin feature/<your-feature-name>
```

---

## 🧾 License

This project is distributed under the MIT License. See `LICENSE` for details.

---

## 🛠️ Tech Stack Summary

| Layer | Technology |
| --- | --- |
| Backend | Laravel (PHP 8.x) |
| Frontend | Next.js (TypeScript + Tailwind CSS) |
| Database | MySQL / PostgreSQL |
| Infrastructure | Terraform + Docker |
| CI/CD | GitHub Actions |
| Cloud | AWS-ready (EC2, S3, RDS) |

---

## 🪶 Maintainer

**Michael Kwame Adjei**  
📍 Frankfurt, Germany  
🎯 Building a scalable, community-driven church SaaS  
🌐 [GitHub Profile](https://github.com/michaelkwameadjei)

---

### 🔍 Explanation of Improvements

| Improvement | Why it matters |
| --- | --- |
| **Badges + emojis** | Adds visual appeal and easy section scanning |
| **Headings and dividers** | Breaks content into digestible chunks |
| **Code fences and tables** | Clarify commands and structures |
| **Contributing section** | Guides collaborators through the proper Git workflow being practised |
| **License + Maintainer** | Makes it public-ready |
| **Tech stack summary table** | Helps recruiters and collaborators understand the system at a glance |

---

### 💡 Next Learning Step on GitHub

Once you commit and push this new `README.md`:

```bash
git add README.md
git commit -m "docs: improve README structure and formatting"
git push
```
