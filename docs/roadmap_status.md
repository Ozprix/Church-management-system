# Roadmap Execution Status

This document tracks progress against the staged implementation plan in `docs/roadmap.md`.
Statuses: ✅ Done · 🔄 In Progress · ⏳ Pending · ⚠️ Blocked.

## Phase 0 – Foundation

| Workstream | Status | Notes & Follow-Ups |
| --- | --- | --- |
| Monorepo scaffolding (pnpm, Laravel, Next.js) | ✅ | Repository layout, bootstrap scripts, and workspace configuration are in place. |
| Tenancy baseline (middleware, global scopes) | 🔄 | Tenant resolver, middleware, and `TenantScoped` trait exist; queue payload handling + automated tests are in place, and tenant CLI helpers (`tenant:run`, `tenant:seed`, `tenant:run-batch`) now cover targeted and batch execution; continue polishing CLI ergonomics and operational docs. |
| Auth & RBAC | 🔄 | Sanctum login/logout/me endpoints now enforce single-session tokens with TOTP-based 2FA + recovery codes; remaining work includes permission seeding audits and UI surfacing. |
| DevOps bootstrap (CI, Docker images, Terraform skeleton) | ⏳ | Terraform scaffolds present, but CI workflows, container hardening, and secrets management scripts not yet created. |

## Phase 1 – Member & Family Core

| Workstream | Status | Notes & Follow-Ups |
| --- | --- | --- |
| Member CRUD + validation | 🔄 | Controllers, resources, service layer, and feature coverage now include happy-path, validation failure, auditing assertions, filter/search + pagination scenarios, restore support, throttled bulk import/delete, async CSV import queue with monitoring, member audit timeline endpoints, and initial web dashboards (members list, analytics view); remaining UI polish includes richer charting and advanced filters. |
| Custom fields & documents | 🔄 | API endpoints are scaffolded; file storage configuration, validation, and UI wiring pending. |
| Family grouping & household comms | 🔄 | Controllers seeded; orchestration now enforces primary/emergency contacts, triggers role notifications, and exposes `/families/{id}/communications` for broadcast messaging. Remaining work: UI polish and advanced automation rules. |
| Attendance tracking & kiosk mode | 🔄 | API supports check-in workflows and the new `/attendance/kiosk` PWA provides offline check-in with sync queues; remaining work: hardware QR integration and rich analytics. |
| Visitor intake & lifecycle | 🔄 | Automations now queue follow-up steps, expose logs, and surface `/visitors/analytics` dashboards; remaining work: rule builder UI polish and deeper conversion funnels. |
| Reporting (directory, attendance dashboard) | ⏳ | Analytics controllers started, dashboards and exports need polishing and UI integration. |

## Phase 2 – Financial Suite

| Workstream | Status | Notes & Follow-Ups |
| --- | --- | --- |
| Donation intake & Stripe webhooks | 🔄 | Donation service and webhook controller exist; receipt PDFs now generate/email on success. Pending: live gateway integration tests. |
| Pledge management & reminders | ⏳ | Models and services scaffolded, reminder scheduling logic outstanding. |
| Ledger & journal subsystem | ⏳ | Ledger model exists; double-entry enforcement and reconciliation tooling still required. |
| Expense workflow UI | ✅ | Expenses API with submission/approval/reimbursement states plus new finance/expenses PWA section. |
| Financial reports & statements | ✅ | Tenant-branded PDF exports (statements, balance sheet, donor letters) available under Finance &gt; Branded PDFs. |

## Phase 3 – Events & Communications

| Workstream | Status | Notes & Follow-Ups |
| --- | --- | --- |
| Event calendar & resource scheduling | ⏳ | Basic gathering endpoints live; conflict resolution, UI calendar, and notifications not started. |
| Registrations & ticketing | ⏳ | No backend/frontend implementation yet. |
| Notification rules engine | 🔄 | Notification rule service exists; needs UI builder, condition testing, and delivery queue integration. |
| Messaging integrations (SMS/Email) | ⏳ | Service stubs prepared; awaiting provider configuration and retry logic. |
| Prayer request portal | ⏳ | Not yet implemented. |

## Phase 4 – Engagement & Volunteer Management

| Workstream | Status | Notes & Follow-Ups |
| --- | --- | --- |
| Internal messaging & chat | ⏳ | No work started; requires websocket stack. |
| Volunteer opportunities & scheduling | 🔄 | Volunteer services/controllers implemented with seed data; still need PWA experience, reminders, and analytics UI. |
| Engagement analytics dashboards | ⏳ | Requires KPIs, charting components, and data aggregation jobs. |

## Phase 5 – Hardening & Launch Prep

| Workstream | Status | Notes & Follow-Ups |
| --- | --- | --- |
| Billing & subscription enforcement | 🔄 | Plan enforcement service exists; billing integration (Stripe subscriptions) pending. |
| Security hardening & 2FA | ⏳ | Policies defined in docs; implementation/tests outstanding. |
| Performance & load testing | ⏳ | No tooling configured yet. |
| Documentation & onboarding | 🔄 | Architecture docs available; end-user guides and onboarding checklists to produce. |
| Beta launch readiness | ⏳ | Depends on completion of earlier phases. |

## Immediate Next Actions

- Implement tenant-level policies for requiring 2FA on high-privilege roles and expose compliance reporting.
- Prioritize member module feature tests and auditing hooks to close Phase 1 gaps.
- Extend tenant CLI helpers for batch execution (e.g., run commands across many tenants) and document operational playbooks.
- Monitor the new API CI workflow and extend coverage (linting, parallelisation) once the baseline stabilises.
