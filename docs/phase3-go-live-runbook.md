# Phase 3 Go-Live Runbook

_Covers calendar, ticketing, and notification automation rollout._

## 1. Pre-flight Checklist

1. **Deployments**
   - API (`apps/api`) + web PWA (`apps/web`) released from `main`.
   - Database migrations applied (notification metrics, ticketing tables).
2. **Feature Flags**
   - `attendance`, `notifications`, `notifications_automation` features enabled per tenant.
3. **Tenancy Config**
   - `TENANT_ID`, `NEXT_PUBLIC_TENANT_ID`, `X-Tenant-ID` header wired in CLI + hosting layers.

## 2. Provider Keys

| Channel | Required ENV | Notes |
| --- | --- | --- |
| **SMS (Twilio)** | `TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_FROM`, `TWILIO_MOCK=false` | Provision messaging service, request 10DLC if needed, add geo permissions. |
| **Email (Mailgun)** | `MAILGUN_DOMAIN`, `MAILGUN_SECRET`, `MAILGUN_FROM`, `MAILGUN_MOCK=false` | Verify sending domain + DKIM, update `mail.from.*` fallback. |
| **Stripe (tickets)** | `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_MOCK=false` | Re-run `php artisan queue:work` to process ticket payments. |

**Verification**
1. Rotate keys in secrets manager.
2. `php artisan config:clear` + redeploy queue workers.
3. Hit `GET /api/v1/notifications/health` – `provider.configured` should flip to `true`.

## 3. Template Management

1. Seed starter templates via `php artisan tenants:seed --class=NotificationTemplateSeeder`.
2. In `/notifications/rules`, confirm template library loads (100 per page).
3. Document naming convention: `<channel>-<use-case>-v#` (e.g., `sms-firstvisit-v1`).
4. Store approved copy in `docs/templates/<slug>.md`.

## 4. Calendar & Ticketing Cutover

1. **Calendar**
   - Confirm `/events/calendar` loads seeded data.
   - Run drag/drop smoke: conflict + success (see `docs/phase3-calendar-smoke.md`).
   - Verify quick-edit persists to `/events/{uuid}` detail screen.
2. **Ticketing**
   - For each launch event, create ticket types (capacity > 0).
   - Run `php artisan gathering:sync-ticketing --tenant=<uuid>` if migrating data.
3. **Registrations**
   - Execute `php artisan gathering:seed-registrations --tenant=<uuid>` for test data.
   - Confirm check-in actions update counts in `/events/{uuid}`.

## 5. Notification Rules & Delivery Health

1. Populate baseline rules (birthdays, visitor follow-up) via `/notifications/rules`.
2. For each rule:
   - Manual run against staging tenant.
   - Check `Recent runs` table for status + counts.
3. Monitor `Notifications > Channel health` cards:
   - SMS or Email should show `health: healthy` after 10+ sends with <15% failures.
   - Investigate `last_error` via Horizon logs or provider dashboards.

## 6. KPI Dashboards

| Area | Metric | Source |
| --- | --- | --- |
| Calendar | Event count, conflicts resolved | `/events/calendar` query + conflict API logs. |
| Ticketing | Tickets sold vs capacity, check-ins | `gatherings.ticket_types`, registrations table. |
| Notifications | Sent vs failed per channel | `notification_metrics`, `GET /api/v1/notifications/health`. |
| Automation | Rule run cadence | `notification_rule_runs`. |

1. Build Grafana/Metabase widgets off the above tables.
2. Alert thresholds:
   - SMS failure rate > 20% (per hour) → Slack alert.
   - Ticket check-in lag > 15 mins after start.

## 7. Cutover Timeline

| Time | Task | Owner |
| --- | --- | --- |
| T-60m | Freeze Phase 3 repos, tag release | Eng Lead |
| T-45m | Rotate provider keys, restart queues | DevOps |
| T-30m | Run smoke scripts (calendar, tickets, notifications) | QA |
| T-10m | Notify support, enable customer access | CS |
| T+30m | Review dashboards, log incidents | On-call |

## 8. Rollback Plan

1. Toggle feature flags `notifications` + `notifications_automation` off per tenant.
2. Restore previous release tag (`deploy --rollback vX.Y`).
3. Revert `.env` secrets if key rotation suspected.
4. Clear queues: `php artisan queue:flush` (per worker) to drop invalid jobs.
5. Post-mortem template stored in `docs/postmortems/`.
