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
| Member CRUD + validation | ✅ | End-to-end flows cover validation, auditing, bulk operations, analytics dashboards, and QA regression. Remaining enhancements (advanced filtering, richer charts) tracked as Phase 1.1 polish tasks. |
| Custom fields & documents | ✅ | Admins can configure custom fields with file-type controls; member profiles expose document uploads with inline errors and download links. |
| Family grouping & household comms | ✅ | Households manage assignments/contacts, analytics highlight follow-up gaps, and document storage supports pastoral coordination. Future automations graduate to Phase 2. |
| Attendance tracking & kiosk mode | 🔄 | API supports check-in workflows, the `/attendance/kiosk` PWA provides offline sync, `/attendance/analytics` surfaces trends + exports, and saved reports (`/attendance/reports`) now schedule recurring summaries; remaining work: hardware QR integration. |
| Visitor intake & lifecycle | ✅ | Rule builder, staff email steps, and contextual automation checks are live; analytics and funnels surface conversion metrics, with logs noting skipped rules. |
| Reporting (directory, attendance dashboard) | ⏳ | Analytics controllers started, dashboards and exports need polishing and UI integration. |

## Phase 2 – Financial Suite

| Workstream | Status | Notes & Follow-Ups |
| --- | --- | --- |
| Donation intake & Stripe webhooks | ✅ | Stripe webhooks covered; live Stripe integration test now runs when credentials are present, confirming payment intents end-to-end. |
| Pledge management & reminders | 🔄 | Reminder scheduler/queue tested end-to-end (tenant + limit options, auto-disable on fulfilment); pledge reminder UI lives under Finance > Pledge reminders and is gated by `finance.manage_pledges`; remaining work focuses on campaign UI & reminder UX polish. |
| Ledger & journal subsystem | ✅ | General ledger enforcement now posts balanced entries for donations and reimbursements; monthly trial balance tooling verifies double-entry integrity per tenant. |
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

- Run the Phase 1 QA checklist (see `docs/phase1-qa-checklist.md`) across members, families, custom fields, attendance, and visitor flows.
- Capture any regressions or UX polish items as Phase 1.1 follow-ups.
- Implement tenant-level policies for requiring 2FA on high-privilege roles and expose compliance reporting.
- Extend tenant CLI helpers for batch execution (e.g., run commands across many tenants) and document operational playbooks.
- Monitor the API CI workflow and extend coverage (linting, parallelisation) once the baseline stabilises.
