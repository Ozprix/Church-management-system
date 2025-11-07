# Communications Dashboard Plan

## Objectives
- Give communications staff a single pane to monitor delivery health, rule execution, and engagement.
- Surface actionable alerts (e.g., SMS failures, stuck queues) without digging into logs.
- Provide KPIs for leadership (delivery rates, response time, prayer follow-ups).

## Data Sources
| Metric | Source | Notes |
| --- | --- | --- |
| Channel health (SMS/email) | `GET /api/v1/notifications/health` | Already implemented; returns queued/sent/failed + provider config. |
| Notification queue backlog | `notifications` table (`queued`/`sending` counts). | Add API endpoint `/api/v1/notifications/metrics` with per-channel queue size + oldest queued timestamp. |
| Rule runs | `notification_rule_runs` | Use existing `runs` relation; expose summary endpoint `/api/v1/notification-rules/runs?days=7`. |
| Prayer requests | `prayer_requests` (planned) | Counts by status, time-to-first-response once portal ships. |
| Email/SMS engagement | `notification_metrics` | Daily aggregates for charts (sent vs delivered vs failed). |
| Ticketing comms | `gatherings.registrations` + notification logs | Later: track event reminder sends vs check-ins. |

## Dashboard Layout (PWA `/communication/dashboard`)
1. **Channel Health Cards** (2-up): reuse data from `/notifications/health`, include CTA to configure providers if missing keys.
2. **Queue Monitor**:
   - Bar showing queued vs sending per channel.
   - Badge highlighting "oldest queued" age; clicking opens `/notifications` filtered view.
3. **Rule Activity**:
   - Table listing last 5 manual runs + next scheduled automation (when scheduler lands).
   - Sparkline showing matches vs sent per day.
4. **Prayer Pipeline** (future tie-in): stacked bars (New, In Progress, Answered) and SLA widget (avg minutes to first response).
5. **Event Reminder Performance** (phase 3.1): chart comparing reminders sent vs day-of check-ins.
6. **Alerts Panel**: feed of recent failures (top `notifications.failed` rows) + provider error messages.

## API Work Needed
- `GET /api/v1/notifications/metrics?days=7` → returns per-channel totals, queue depth, oldest queued timestamp.
- `GET /api/v1/notification-rules/runs?days=7` → aggregated counts for sparkline.
- `GET /api/v1/prayer/requests/metrics` (once schema exists) → counts + avg SLA.
- Extend `notification_metrics` job to populate `delivered`/`failed` counts hourly so charts have granularity.

## UI Components
- Reuse `Card` + `StatCard` from `@church/ui`.
- Charting via existing Recharts area/line components (already used in analytics pages).
- Hook into React Query with 60s stale time; show skeleton states on initial load.
- Add filter bar (tenant timezone, date window).

## Alerts & Notifications
- Trigger toast/banner if a provider is unconfigured or failure rate > threshold.
- Optionally wire Slack webhook from backend when `failed/total > 0.2` over past 5 mins.

## Next Steps
1. Build `/api/v1/notifications/metrics` endpoint.
2. Scaffold `/communication/dashboard` page consuming health + metrics endpoints.
3. Add `prayer` metrics once portal is live.
