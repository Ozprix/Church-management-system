# Members & Families Module Design

## 1. Domain Overview
- **Goal:** Manage individual members, their contact information, family relationships, and lifecycle milestones with strict tenant isolation.
- **Tenancy:** Every record contains `tenant_id` and leverages `TenantScoped` for automatic filtering.
- **Key Entities:** `Member`, `MemberContact`, `MemberCustomField`, `MemberCustomValue`, `Family`, `FamilyMember`, `AttendanceRecord`, `VisitorLog`.
- **Integrations:** Hooks into Communication module for birthday/reminder triggers; Finance module for donor association; Analytics dashboards for attendance and engagement KPIs.

## 2. Data Model
### Member
| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | Auto increment |
| `tenant_id` | FK | Scoped to tenant |
| `uuid` | UUID | Public identifier |
| `first_name`, `last_name`, `middle_name` | string | Nullable middle name |
| `preferred_name` | string | Optional |
| `gender` | enum | `male`, `female`, `non_binary`, `unspecified` |
| `dob` | date | Nullable |
| `marital_status` | enum | `single`, `married`, `divorced`, `widowed`, `separated`, `partnered` |
| `membership_status` | enum | `prospect`, `active`, `inactive`, `visitor`, `suspended`, `transferred` |
| `membership_stage` | string | Workflow stage |
| `joined_at` | datetime | When membership activated |
| `photo_path` | string | Storage path |
| `notes` | text | Pastoral notes |
| `created_by` / `updated_by` | FK | User reference |
| Timestamps & soft deletes |

### MemberContact
Stores multiple contact methods with preferences.
- `member_id`, `type` (`email`, `mobile`, `home_phone`, `address`, `social`, `other`)
- `value` (string / json for address), `label`, `is_primary`, `is_emergency`, `communication_preference` (enum: `email`, `sms`, `call`, `mail`).

### MemberCustomField & MemberCustomValue
Allows tenant-defined attributes. Fields define `data_type` (`text`, `number`, `date`, `boolean`, `select`, `multi_select`), `config` (JSON for options/validation). Values capture normalized data with appropriate typed columns.

### Family & FamilyMember
- **Family:** Household grouping with `family_name`, `address_id`, `photo_path`.
- **FamilyMember:** Join table linking members to families with `relationship` (`head`, `spouse`, `child`, `dependent`, `guardian`, `relative`, `other`), `is_primary_contact`, `is_emergency_contact`.
- Supports multiple family memberships per individual (e.g., blended households).

### Lifecycle Tables
- `membership_processes`, `member_process_runs`, `process_stage_logs` (already outlined in database doc) integrate with this module; initial implementation will expose readonly endpoints for stage progress.

### Attendance
`attendance_records` capture check-in per event/meeting with `status` (`present`, `absent`, `excused`); integrated via Events module but accessible through Member detail.

## 3. Validation & Business Rules
- Member names required (`first_name`, `last_name`).
- Either primary email or phone required for active members.
- At most one primary contact per contact type per member.
- Families must have at least one `head` or `guardian` flagged as primary contact.
- Custom field data validated against `data_type` and allowed options.
- Soft delete cascade: deleting a member should soft-delete contacts, custom values, family pivot entries.

## 4. API Surface (Laravel Controllers)
Base path: `/api/v1/members` (protected by `tenant.resolve`, `auth:sanctum`, and `can:view-members` policy).

### Members
- `GET /members` — list with filters (status, ministry involvement, age range, search by name/email/phone).
- `POST /members` — create member (basic info, contacts, family assignment).
- `GET /members/{uuid}` — detail view including contacts, families, lifecycle stages, attendance summary.
- `GET /members/{uuid}/audits` — paginated activity history (create/update/delete/import actions, actor, payload snapshot) for the member detail timeline.
- `PUT /members/{uuid}` — update base info and nested contacts.
- `DELETE /members/{uuid}` — soft delete.
- `POST /members/{uuid}/photo` — upload profile picture (S3 path stored in `photo_path`).
- `POST /members/{uuid}/restore` — restore soft-deleted member.

### Families
- `GET /families` — list families with aggregated member counts.
- `POST /families` — create household and members assignments.
- `GET /families/{id}` — detail with members + roles.
- `PUT /families/{id}` — update household info, reassign roles.
- `DELETE /families/{id}` — soft delete (if no active members or by force flag).

### Custom Fields
- `GET /members/custom-fields`
- `POST /members/custom-fields`
- `PUT /members/custom-fields/{id}`
- `DELETE /members/custom-fields/{id}` (prevent delete if values exist unless `force=true`).

### Lifecycle / Attendance
- `GET /members/{uuid}/lifecycle` — returns process run details.
- `GET /members/{uuid}/attendance` — paginate recent attendance records with filters.

### Visitor Onboarding
- `POST /visitors` — create visitor record + potential auto-member conversion pathway.
- `POST /members/{uuid}/convert-visitor` — convert visitor to active member (updates status + joined_at).
- `POST /member-imports` — upload CSV to queue async import; `GET /member-imports` & `GET /member-imports/{id}` expose job status/metrics.

## 5. Service Layer & Jobs
- `MemberService`
  - `createMember(array $data): Member`
  - `updateMember(Member $member, array $data): Member`
  - Handles contact upsert, family assignments, lifecycle hooks, event dispatches.
- `FamilyService`
  - Ensures primary contacts, merges households, handles role changes.
- Jobs/Listeners: send welcome email, notify pastoral care, trigger communication workflows.

## 6. Policies & Permissions
- Permissions: `view-members`, `manage-members`, `manage-families`, `manage-custom-fields`.
- Policies confirm user’s tenant and role; e.g., finance managers may have read-only access, pastoral staff get full CRUD.

## 7. Frontend Touchpoints (Next.js PWA)
- Pages: `/members`, `/members/[uuid]`, `/families`, `/families/[id]`.
- Analytics: `/members/analytics` dashboard summarising KPIs with export actions.
- Components: Member list with filters, profile tabs (Overview, Families, Communication, Attendance), Family tree visualization.
- API client integration using `@church/contracts` generated endpoints + React Query.
- Form handling via React Hook Form + Zod validated against backend rules.

## 8. Analytics & Reporting
- KPIs: active members count, visitor retention rate, attendance streak, family households by status.
- Export endpoints: `GET /members/export?format=csv|pdf`. Offload heavy exports to queue + storage for download.
- Analytics dashboards: backend endpoints `/members/analytics`, `/families/analytics`, `/finance/analytics` feed the Next.js pages under `/members/analytics`, `/families/analytics`, and `/finance/analytics` for live KPIs + CSV exports.

## 9. Notifications & Automations
- Domain events: `MemberCreated`, `MemberUpdated`, `MemberStatusChanged`, consumed by Communications module to trigger emails/SMS.
- Automated tasks: daily job runs to check birthdays, membership anniversaries, absence thresholds (dependent on Notification module configuration).

## 10. Implementation Roadmap (Incremental)
1. **Sprint 1**: Members CRUD (info + contacts), listing filters, API tests.
2. **Sprint 2**: Families management & assignments, relationships UI.
3. **Sprint 3**: Custom fields + values, CSV import/export baseline.
4. **Sprint 4**: Visitor conversion and lifecycle integration, notifications.
