# Phase 1 QA Results (Release Candidate Review)

This report captures the current QA state for the **Member & Family Core** milestone. Automated coverage has been re-run after fixing the seed data blockers, and manual scenarios are documented with their pass/fail status plus follow-up actions for the Phase 1 polish sprint.

## Test Environment
- Backend: `apps/api` on commit `feature/auth-signin` (local SQLite).
- Seeding: `php artisan migrate:fresh --seed` – **Pass** after updating `DatabaseSeeder` and `FinanceSeeder`.
- Queue driver: temporarily set to `sync` to avoid the Redis extension requirement during local runs.

## Automated Coverage
- `php artisan test --testsuite=Feature` – **Pass** (165 tests, 796 assertions, 17.53 s).
- Areas exercised: Members CRUD + analytics, Families analytics/export, Attendance reports/exports, Visitor workflows, Volunteer pipeline APIs, Finance donations/receipts, RBAC/tenancy tooling.

## Manual Checklist Outcomes

| Area | Status | Notes |
| --- | --- | --- |
| Test preparation | ✅ | Admin seeding fixed; custom-field/report storage confirmed via config (`apps/api/.env`, `config/filesystems.php`). |
| Member management | ⚠️ | API coverage exists; UI flows to add/edit members and upload household documents still need hands-on validation in the PWA. |
| Families & analytics | ⚠️ | Metrics/exports validated via API tests; need UI confirmation that analytics dashboards reflect live edits and CSV export matches expectations. |
| Attendance & kiosk | ❌ | Backend analytics verified; kiosk offline sync and hardware QR reader remain untested manually (no browser session executed yet). |
| Visitor automation | ⚠️ | Workflow creation/dispatch validated via tests; rule-builder skip logic and staff notifications require UI verification. |
| Regression smoke (navigation, exports) | ❌ | Member analytics export, custom-field admin validation UI, and role-based navigation not yet exercised in the browser. |

Legend: ✅ Pass · ⚠️ Partially verified · ❌ Not yet exercised.

## Defects & Follow-Ups (Phase 1 Polish)

| ID | Area | Type | Description | Owner | Status |
| --- | --- | --- | --- | --- | --- |
| P1-001 | Attendance kiosk | Feature gap | Hardware QR integration not implemented; required for kiosk acceptance. | Product | Open |
| P1-002 | Attendance kiosk | QA | Offline kiosk flow not yet validated; needs manual walkthrough once web app is running. | QA | Open |
| P1-003 | Member management | QA | Household document uploads (UI) require verification of file-type/size validation and download links. | QA | Open |
| P1-004 | Families analytics | QA | Ensure dashboard metrics update after household assignments and CSV export contains new data. | QA | Open |
| P1-005 | Visitor automation | QA | Confirm rule-builder skips log appropriately and coordinator notifications display in UI. | QA | Open |
| P1-006 | Navigation & permissions | QA | Validate global nav visibility for admin vs pastoral roles within the PWA. | QA | Open |

## Recommendations
1. Spin up the Next.js PWA (`pnpm dev` in `apps/web`) and capture evidence for the outstanding UI scenarios above.
2. Once Redis is available locally, revert `QUEUE_CONNECTION=redis` in `apps/api/.env` and re-run selective flows to ensure background jobs behave consistently.
3. Log any new defects discovered during manual verification under the **Phase 1 polish** milestone and update this report accordingly.

