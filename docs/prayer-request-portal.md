# Prayer Request Portal – Design Brief

## Goals
1. **Capture requests** from members or the public with privacy controls.
2. **Route & triage** requests to staff/volunteer intercessors.
3. **Track follow-up** (status, notes, notifications, analytics).

## Key Personas
- **Member** – authenticated congregant submitting a request.
- **Public Visitor** – anonymous or email-only submissions embedded on the church site.
- **Prayer Coordinator** – staff user managing triage, assignments, updates.
- **Volunteer Intercessor** – user with permission to view assigned requests and log outcomes.

## Data Model

### prayer_requests
| Column | Type | Notes |
| --- | --- | --- |
| tenant_id | bigint | Tenant scope. |
| uuid | uuid | Public identifier (used in links). |
| member_id | bigint nullable | Link to member when authenticated. |
| submitted_by | bigint nullable | User ID of staff-created entry. |
| title | string(160) | Short summary. |
| details | text | Full request details (Markdown allowed). |
| visibility | enum(`public`,`members`,`staff`) | Controls who can view; `public` = anonymized wall, `members` = logged-in area, `staff` = internal only. |
| status | enum(`new`,`in_progress`,`answered`,`archived`) | Triage state. |
| requested_confidentiality | boolean | Respect privacy when sharing updates. |
| tags | json | Topical tags for filtering (healing, finances, missions, etc.). |
| follow_up_notes | text nullable | Staff notes. |
| answered_at | timestamp nullable | When request marked answered. |
| created_at / updated_at | timestamps | |

### prayer_assignments
| Column | Type | Notes |
| --- | --- | --- |
| tenant_id | bigint | |
| prayer_request_id | bigint | FK. |
| assigned_to_user_id | bigint nullable | Staff/volunteer (users table). |
| assigned_to_team_id | bigint nullable | Volunteer team/ministry (optional). |
| status | enum(`pending`,`accepted`,`completed`,`declined`) | |
| due_at | timestamp nullable | SLA for follow-up. |
| completed_at | timestamp nullable | When follow-up logged. |
| notes | text nullable | Intercessor updates. |

### prayer_updates (optional extension)
| Column | Notes |
| --- | --- |
| prayer_request_id | FK |
| body | Update text (Markdown) |
| visibility | matches parent |
| created_by | user_id |

## API Endpoints (v1 draft)

| Method | Path | Description | Auth |
| --- | --- | --- | --- |
| POST | `/api/v1/prayer/requests` | Create request. Accepts member ID or contact info; respects rate limiting. | Public (captcha) + Auth |
| GET | `/api/v1/prayer/requests` | List with filters (status, tag, visibility). Paginates. | Auth (feature flag `prayer_portal`). |
| GET | `/api/v1/prayer/requests/{uuid}` | View single request (permission-checked). | Auth + visibility check. |
| PATCH | `/api/v1/prayer/requests/{uuid}` | Update status, notes, tags, visibility. | `prayer.manage` |
| POST | `/api/v1/prayer/requests/{uuid}/assignments` | Assign to user/team. | `prayer.manage` |
| PATCH | `/api/v1/prayer/assignments/{id}` | Accept/complete/decline assignment. | Assignee |
| POST | `/api/v1/prayer/requests/{uuid}/updates` | Add public/member update. | `prayer.manage` |

**Public Form Endpoint**
- `POST /api/v1/prayer/requests/public`: accepts name/email (optional), message, consent checkbox.
- Spam protection: `hcaptcha_token`, rate-limit per IP, optional email verification.

## Permissions / Feature Flags
- Feature: `prayer` gating the module.
- Permissions:
  - `prayer.view` – read-only access, limited to visibility scope.
  - `prayer.manage` – create/update requests, manage visibility.
  - `prayer.assign` – assign volunteers.
  - `prayer.respond` – update assigned requests.

## UI Flow

### Public Submission (PWA)
1. `/prayer/request` route with simple form.
2. Confirmation screen referencing request ID (optional).
3. optional: email auto-response with tracking link (if email provided).

### Staff Portal
1. `/prayer` index view:
   - Filters: status, visibility, tag, timeframe.
   - Board layout: `New`, `In Progress`, `Answered`.
   - Each card shows submitter, tags, confidentiality badge.
2. Triage drawer:
   - Edit visibility & status.
   - Assign to team/user (multi-select).
   - View history (assignments, updates).
3. Public/member wall management: toggle which answered requests appear on `/prayer/wall`.

### Volunteer Dashboard
1. `/prayer/assignments` list:
   - Accept/decline (with reason).
   - Mark as prayed/complete, add optional note visible to staff.
2. Notifications: rules can trigger when a new request hits their team.

## Integrations
- **Notifications:** On new request → send to staff group; on assignment → notify assignee; on answered → optional message to submitter.
- **Analytics:** track number of requests per week, time-to-first-response, answered ratio.
- **Calendar:** optionally schedule follow-up calls as events (future iteration).

## Open Questions
- Do we surface anonymous requests publicly? Need tenant-level setting (`tenant_settings.prayer`).
- Should members be able to subscribe to updates (email digest)? Possibly reuse notification rules.
- Multi-campus filter (if tenant uses campuses).

## Next Steps
1. Add migrations for `prayer_requests`, `prayer_assignments`, `prayer_updates`.
2. Wire API controllers + policies.
3. Build `/prayer` pages (public + staff) with Kanban UI.
4. Hook notifications + analytics dashboards once core CRUD solid.
