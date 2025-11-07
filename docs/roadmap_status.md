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
| Event calendar & resource scheduling | ✅ | `/events/calendar` delivers drag/drop rescheduling, inline quick edit, and a keyboard-accessible “New event” modal; see `docs/phase3-calendar-smoke.md` for the latest test run. |
| Registrations & ticketing | ✅ | Ticket type CRUD, attendee check-in, and capacity enforcement now surface inside the PWA (detail page + manage link from calendar). |
| Notification rules engine | ✅ | UI builder, manual runs, and recent-run history ship alongside the automation service; delivery queues reuse the same engine and are live in the dashboard. |
| Messaging integrations (SMS/Email) | ⏳ | Service stubs prepared; awaiting provider configuration and retry logic. |
| Prayer request portal | ⏳ | Design brief drafted (`docs/prayer-request-portal.md`) covering API, permissions, UI; next up is schema + endpoint implementation. |

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

- Socialize the calendar/ticketing/notification go-live runbook (`docs/phase3-go-live-runbook.md`) with support + CS, capture tenant-specific deltas.
- Begin SMS/email provider integrations, surfacing delivery status + retry dashboards inside `/notifications/rules`.
- Define the prayer request portal flow (public form, staff triage inbox, notification routing) and capture API contracts.
- Expand automated coverage: add calendar interaction tests (drag/drop + modal) and queue them in the Node 20 CI job.
- Stand up analytics dashboards for launch KPIs (adoption, comms delivery, ticket sell-through) using the existing chart components.
