# Church Management SaaS – Architecture Blueprint

## 1. Vision & Core Principles
- **Product Goal:** Deliver a multi-tenant church management SaaS with strict tenant isolation, rich member engagement tooling, and financial stewardship features fit for global churches.
- **Guiding Principles:** Security-first, modular domain boundaries, automation-friendly APIs, observability baked-in, and progressive enhancement for the web experience.
- **Tenancy Model:** Single codebase, shared infrastructure, row-level isolation by `tenant_id` enforced in database, ORM, and policy layers. Supports dedicated resources for premium tenants via configuration flags.

## 2. High-Level System Overview
```
[PWA Client (Next.js + Tailwind)] <---> [API Gateway (Laravel HTTP)]
        |                                        |
    Service Workers                           Modules (Members, Finance, Events, Comms, Volunteers, Admin)
        |                                        |
 Offline Cache & Notifications             Service Layer + Repositories + Policies
                                                 |
                                       MySQL 8 Cluster (tenant_id indexed)
                                                 |
                                        Redis (cache, queues)
                                                 |
                                  Async Workers & Event Bus (Laravel Queues)
                                                 |
                               Integrations (Stripe, PayPal, Twilio, Mailgun)
```

## 3. Frontend (PWA) Architecture
- **Stack:** Next.js 14 (React Server Components) + TypeScript + Tailwind CSS + Zustand/Redux Toolkit for state.
- **Multi-Tenant UX:** Tenant discovery via subdomain (`tenant.slug.app.com`) or custom domain. Tenant context stored in secure HTTP-only cookie and provided to API as header.
- **Routing:** App Router with nested layouts per domain module; dynamic segments for tenant+resource.
- **Offline Support:** Service worker caches critical assets, queued mutations using IndexedDB. Background sync for attendance check-ins.
- **UI Kits:** Tailwind with Headless UI, Chart.js for analytics, Recharts for dashboards. Component library separated under `packages/ui` for reuse.
- **Access Control:** Client reads auth scope from JWT/Session token to toggle navigation; no sensitive logic on client side.

## 4. Backend Architecture (Laravel 11)
- **Patterns:** Domain-driven modular structure (`app/Modules/<Domain>`). Each module exposes controllers, jobs, policies, resources, observers.
- **API:** RESTful JSON APIs via Laravel controllers + API Resources. Versioned base path (`/api/v1`). Rate limited per tenant and per user.
- **Service Layer:** Use service classes for business workflows (e.g., `MembershipLifecycleService`). Orchestrates transactions across repositories.
- **Repository Layer:** Encapsulate Eloquent queries with scoped `tenant_id`. Shared `TenantScopedModel` base enforces global scope + tenant-aware factories.
- **Queue & Events:** Laravel events for domain actions, queued listeners for notifications, reporting, sync to integrations.
- **Scheduling:** Laravel scheduler for renewals, reminders, pledge reminders, backups.
- **Storage:** Use S3-compatible storage (e.g., AWS S3, DigitalOcean Spaces). Tenancy-specific folders `tenants/{tenant_id}/...`.

## 5. Multi-Tenancy & Row-Level Security
- **Tenant Context Resolution:** Middleware inspects subdomain, custom domain, or `X-Tenant-ID` header. Validates subscription status, loads tenant config into request container.
- **Database Enforcement:**
  - Global scopes enforce `tenant_id = currentTenant()` on all Eloquent models implementing `TenantScoped` trait.
  - MySQL views restrict read access in reporting by exposing only tenant-filtered views when using BI tools.
  - Stored procedures require `tenant_id` parameter and include assertion checks (`SIGNAL SQLSTATE '45000'` on mismatch).
  - DB user accounts per service (API, reporting) with limited grants to enforce view usage.
- **Application Policies:** Laravel authorization gates double-check `tenant_id` matches the authenticated user's tenant.
- **Background Jobs:** Job payloads carry `tenant_id`; `TenantAware` job middleware switches tenant context before running.

## 6. Domain Modules
- **Directory Pattern:** `app/Modules/<Domain>` with subfolders `Http`, `Models`, `Services`, `Policies`, `Events`, `Listeners`, `DTOs`.
- **Members & Families:** Handles profiles, custom fields, attachments (S3), households, attendance, lifecycle workflows.
- **Finance:** Donations, pledges, expenses, automated reconciliations, statements. Uses double-entry ledger tables for audit trail.
- **Events & Calendar:** Event CRUD, registrations, attendance tracking, room/resource scheduling conflicts resolver.
- **Communications:** Notification rules engine, SMS/email integrations, prayer requests, internal messaging with websockets (Laravel Echo + Pusher-compatible server like Laravel WebSockets).
- **Volunteers:** Role postings, scheduling, shift confirmations, hours log, auto-reminders.
- **Admin & RBAC:** Role templates, permission management, tenant subscription settings, audit logs.

## 7. Integrations & External Services
- **Payments:**
  - Stripe for cards; PayPal optional; Mobile money via Flutterwave/Momo integrations layer.
  - Webhook handlers per tenant, verifying signatures, storing events in `payment_events` table.
- **Messaging:**
  - SMS via Twilio/Vonage wrapper service. Fallback to cheaper gateways by region.
  - Email via Mailgun/SendGrid. Use per-tenant sender domains & templates stored in DB.
- **Cloud Storage:** Prefer AWS S3; Netlify used for frontend hosting + serverless functions for webhooks if desired.
- **Infrastructure as Code:** Terraform modules for VPC, RDS MySQL, ElastiCache/Redis, S3 buckets, ACM certs.

## 8. Security Architecture
- **Authentication:**
  - Laravel Breeze/Fortify for base auth + Laravel Passport or Sanctum for SPA tokens.
  - Supports session-based auth for web + token-based for mobile/PWA.
  - Two-Factor via TOTP (Google Authenticator) or SMS fallback.
- **Password Policies:** NIST compliant length checks, password history stored hashed, breach detection via HaveIBeenPwned API (cached).
- **Session Hardening:** Secure, HttpOnly cookies; same-site `lax`; rotated tokens on privilege change.
- **Brute-Force Protection:** Rate limiting, captcha after threshold, IP throttling via Laravel RateLimiter.
- **Encryption:** Laravel's at-rest encryption for sensitive fields; disk encryption (S3 SSE); TLS 1.2+ on ingress.
- **Compliance:** GDPR-ready data export/delete endpoints, audit trails, configurable data retention.

## 9. Observability & Operations
- **Logging:** Monolog to JSON logs shipped to ELK/OpenSearch. Structured `tenant_id`, `user_id`, `request_id` for traceability.
- **Metrics:** Prometheus instrumentation via Laravel Prometheus exporter (requests, job latency, queue depth).
- **Tracing:** OpenTelemetry SDK capturing spans from frontend (browser) and backend -> Jaeger/Tempo.
- **Alerts:** PagerDuty/Slack alerts for high error rate, slow queries, payment webhook failures.
- **Backups:** Nightly automated RDS snapshots + point-in-time recovery. File backups via lifecycle rules.

## 10. DevEx & CI/CD
- **Repository Layout:** Monorepo with pnpm workspaces (frontend + shared packages) and Composer/Laravel app. Use Turborepo or Nx for pipeline orchestration.
- **Local Dev:** Sail/Docker Compose for Laravel (PHP-FPM, MySQL, Redis), Next.js dev server, Mailpit, Meilisearch (for search features).
- **Testing:**
  - Backend: PHPUnit, Pest, Feature tests with tenancy fixtures, API contract tests (Pact).
  - Frontend: Vitest + React Testing Library, Playwright for e2e flows.
  - Security: Automated dependency scans (GitHub Dependabot, Snyk), static analysis (Larastan, PHPStan), ESLint.
- **CI/CD:** GitHub Actions workflows for lint/test/build/deploy. Blue/green deployments on Laravel Vapor/Forge or Kubernetes (EKS). Frontend auto-deploy to Netlify with tenant-aware envs.

## 11. Scalability & Future Enhancements
- Horizontal scale API via Kubernetes pods; sticky sessions not required (stateless).
- Plan for microservices split (e.g., Payments Service) when load warrants; use domain events to decouple.
- Support GraphQL API for partner integrations later, layered atop service layer.
- Mobile app (React Native) can reuse REST endpoints + offline sync strategy.

