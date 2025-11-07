# Phase 3 – Calendar & Ticketing Smoke Test

Date: 2025-02-14  
Environment: pnpm workspace, Next.js dev server on Node 20, Laravel API (`php artisan serve`) with seeded calendar data.

## Scope

- Calendar drag/drop reschedule (success + conflict rollback)
- Inline quick edit validation
- New event modal (current and adjacent months)
- Ticketing regression (manage link opens existing `/events/[uuid]`)

## Results

| Scenario | Status | Notes |
| --- | --- | --- |
| Drag event to empty day | ✅ | Card stays in new slot, success toast appears, hard refresh retains change. |
| Drag event onto conflicting slot | ✅ | Backend rejects with `422`, toast shows conflict copy, card snaps back to original day. |
| Quick edit invalid times | ✅ | End time must be after start; inline error prevents submit until corrected. |
| Quick edit save | ✅ | Fields persist, toast confirms, Manage link navigates to detail page with updated times. |
| Modal defaults (current month) | ✅ | Clicking `+ New` seeds chosen day, 09:00–10:30 window, focus lands on Name. |
| Modal creation (previous month) | ✅ | Event appears optimistically in that month, survives navigation back/forth. |
| Modal Escape / overlay close | ✅ | Hitting `Esc` or clicking backdrop dismisses without state leaks. |

## Follow-Ups

- Larger screens: evaluate week-row height auto-fit once drag handles are added.
- Calendar API: surface service availability metadata so conflicts can highlight before submission.
