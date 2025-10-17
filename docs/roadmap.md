# Implementation Roadmap

_Progress tracker: see `docs/roadmap_status.md` for up-to-date status per workstream._

## Phase 0 – Foundation (Week 0-2)
- **Project Setup:** Initialize monorepo (pnpm + Composer), configure Laravel Sail, Next.js app, shared UI package.
- **Tenancy Baseline:** Implement tenant discovery middleware, `Tenant` model, seeding, and global scope trait.
- **Auth & RBAC:** Stand up Laravel Sanctum auth, role/permission tables, seed default roles, create admin console screens.
- **DevOps Init:** GitHub Actions CI (lint/test), Docker images, Terraform skeleton, secrets management via SSM/Env Vault.

## Phase 1 – Member & Family Core (Week 3-6)
- Member CRUD APIs + UI forms, custom fields, document uploads.
- Family grouping, family roles, household communication preferences.
- Attendance capture (manual + QR), check-in kiosk PWA mode.
- Visitor intake flows, lifecycle process scaffolding.
- Reporting: member directory export, attendance dashboard MVP.

## Phase 2 – Financial Suite (Week 7-12)
- Donation intake (manual + Stripe Checkout webhook integration).
- Pledge campaigns, progress dashboards, automated reminders.
- Ledger & journal subsystem with double-entry enforcement.
- Expense submission + approval workflow UI.
- Financial reports (monthly/quarterly) with CSV/PDF export, donor statements generation.

## Phase 3 – Events & Communications (Week 13-18)
- Event calendar (drag/drop), resource scheduling conflict resolver.
- Registrations, ticketing, volunteer links to events.
- Notification rules engine (birthdays, absentees), templating, scheduling queue.
- SMS/Email integrations with status tracking + delivery reports.
- Prayer request portal & follow-up assignments.

## Phase 4 – Engagement & Volunteer Management (Week 19-22)
- Internal messaging (real-time via Laravel WebSockets), group chat, attachments.
- Volunteer opportunities listing, signup workflow, scheduling and hours logs.
- Engagement analytics dashboards (attendance trends, giving trends, volunteer hours).

## Phase 5 – Hardening & Launch Prep (Week 23-26)
- Tenant billing (Stripe subscriptions), plan enforcement, trial management.
- 2FA enforcement, security hardening audit, penetration testing fixes.
- Load/performance testing, query optimization, caching strategy review.
- Documentation (user guides, API docs), customer onboarding checklists.
- Beta launch with selected tenants, feedback loop.

## Continuous Streams
- **QA & Testing:** Automated tests per feature; nightly regression suite; contract tests for integrations.
- **Observability:** Expand metrics, dashboards, alert tuning as features land.
- **Data Migration:** Tools for importing data (CSV mappers) developed alongside relevant modules.

## Milestone Exit Criteria
- **MVP Launch:** Phase 3 complete plus billing lite, covering core membership + finance + comms.
- **GA Launch:** Phase 5 exit with security/compliance checks signed off, support + SLA readiness.

## Team & Process Notes
- Adopt two-week sprints with demo + retro; maintain backlog per domain.
- Use Domain Experts (e.g., finance SME) for UAT each phase.
- Maintain architectural decision records under `docs/adr/` for key choices.
