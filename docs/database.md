# Database Design – Multi-Tenant MySQL Schema

## 1. Conventions
- `tenant_id` present on all tenant-scoped tables (BIGINT UNSIGNED, indexed, FK to `tenants.id`).
- Soft deletes via `deleted_at` where archival needed.
- Use `created_by`, `updated_by` to link back to users for audit.
- Monetary values stored in smallest currency unit (integer).
- UUIDs for public identifiers (`uuid` column) to avoid predictable IDs in URLs.

## 2. Core Tables
### Tenancy & Access
| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `tenants` | Master record for churches/subscriptions. | `id`, `uuid`, `name`, `slug`, `status`, `plan_id`, `timezone`, `locale` |
| `tenant_settings` | JSON settings per tenant. | `tenant_id`, `category`, `payload` |
| `domains` | Custom domains for tenants. | `tenant_id`, `hostname`, `is_primary` |
| `plans` | Subscription plans features. | `id`, `code`, `limits` (JSON) |
| `users` | Platform users scoped by tenant unless global. | `tenant_id (nullable)`, `role_id`, `email`, `password_hash`, `two_factor_secret`, `status` |
| `roles` | Role templates per tenant with RBAC metadata. | `tenant_id`, `name`, `permissions` (JSON) |
| `permissions` | Canonical permission catalogue. | `key`, `description` |
| `user_role` | Pivot for many-to-many user roles when needed. | `user_id`, `role_id` |

### Members & Families
| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `members` | Individual profiles. | `tenant_id`, `uuid`, `first_name`, `last_name`, `gender`, `dob`, `status`, `membership_stage`, `primary_contact_id` |
| `member_contacts` | Phone/email/address per member. | `member_id`, `type`, `value`, `is_primary`, `communication_preference` |
| `member_custom_fields` | Custom field definitions per tenant. | `tenant_id`, `name`, `data_type`, `config` |
| `member_custom_values` | Value storage pivot. | `member_id`, `field_id`, `value_text`, `value_numeric`, `value_date` |
| `documents` | Uploaded files (polymorphic). | `tenant_id`, `documentable_type`, `documentable_id`, `path`, `label`, `visibility` |
| `families` | Household unit. | `tenant_id`, `uuid`, `family_name`, `primary_address_id` |
| `family_members` | Relationship mapping. | `family_id`, `member_id`, `relationship`, `is_primary_contact` |
| `attendance_records` | Attendance by event/session. | `tenant_id`, `attendable_type`, `attendable_id`, `member_id`, `status`, `recorded_at`, `recorded_by` |
| `visitor_logs` | Track visitors and follow-ups. | `tenant_id`, `uuid`, `name`, `visit_date`, `service_id`, `assigned_to`, `status` |

### Membership Lifecycle
| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `membership_processes` | Templates for onboarding stages. | `tenant_id`, `name`, `definition` (JSON) |
| `member_process_runs` | Per-member process tracking. | `tenant_id`, `member_id`, `process_id`, `current_stage`, `started_at`, `completed_at` |
| `process_stage_logs` | Audit of stage transitions. | `process_run_id`, `stage_key`, `status`, `acted_by`, `acted_at`, `notes` |
| `transfers` | Inbound/outbound transfer records. | `tenant_id`, `member_id`, `type`, `from_church`, `to_church`, `status`, `effective_date` |

### Events & Resources
| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `events` | Services, meetings, classes. | `tenant_id`, `uuid`, `title`, `category`, `start_at`, `end_at`, `location_id`, `capacity`, `visibility` |
| `event_registrations` | Participants registration state. | `tenant_id`, `event_id`, `member_id`, `status`, `check_in_code`, `registered_at` |
| `resources` | Rooms, equipment, vehicles. | `tenant_id`, `name`, `type`, `capacity`, `attributes` (JSON) |
| `resource_bookings` | Scheduling conflicts resolved via unique composite index. | `tenant_id`, `resource_id`, `event_id`, `start_at`, `end_at`, `status` |
| `checkins` | Digital check-in logs (QR/manual). | `tenant_id`, `event_id`, `member_id`, `method`, `timestamp`, `device_id` |

### Communications & Engagement
| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `notification_rules` | Automated triggers (birthdays, absences). | `tenant_id`, `rule_type`, `config`, `status` |
| `notifications` | Outbound messages queue. | `tenant_id`, `channel`, `subject`, `payload`, `scheduled_at`, `status` |
| `sms_messages` | SMS detail + delivery status. | `tenant_id`, `uuid`, `to`, `template_id`, `status`, `provider_message_id`, `error_code` |
| `email_messages` | Email detail. | `tenant_id`, `to`, `subject`, `template_id`, `status`, `provider_message_id` |
| `message_templates` | Reusable templates per tenant. | `tenant_id`, `channel`, `code`, `name`, `content` |
| `prayer_requests` | Prayer submissions + privacy. | `tenant_id`, `uuid`, `member_id`, `title`, `details`, `visibility`, `status`, `follow_up_notes` |
| `prayer_assignments` | Volunteers linked to requests. | `tenant_id`, `request_id`, `assigned_to`, `status`, `updated_at` |
| `threads` | Internal group chats. | `tenant_id`, `uuid`, `type`, `title`, `created_by` |
| `thread_participants` | Users in chat. | `thread_id`, `user_id`, `role`, `last_read_at` |
| `messages` | Chat messages. | `tenant_id`, `thread_id`, `user_id`, `body`, `attachments`, `sent_at`, `read_receipts` (JSON) |

### Finance & Accounting
| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `ledger_accounts` | Chart of accounts per tenant. | `tenant_id`, `code`, `name`, `type`, `parent_id` |
| `ledger_entries` | Double-entry lines. | `tenant_id`, `ledger_account_id`, `journal_id`, `debit`, `credit`, `memo`, `posted_at` |
| `journals` | Group ledger transactions. | `tenant_id`, `uuid`, `journal_type`, `reference`, `status`, `notes`, `posted_by` |
| `donations` | Donation headers. | `tenant_id`, `uuid`, `member_id`, `total_amount`, `currency`, `received_at`, `campaign_id`, `payment_intent_id` |
| `donation_line_items` | Split by fund/campaign. | `donation_id`, `fund_id`, `amount`, `memo` |
| `funds` | Designated funds. | `tenant_id`, `uuid`, `name`, `restricted`, `description` |
| `pledges` | Pledge commitments. | `tenant_id`, `uuid`, `member_id`, `campaign_id`, `target_amount`, `frequency`, `start_date`, `end_date` |
| `pledge_payments` | Tracking pledge fulfillment. | `pledge_id`, `donation_id`, `amount`, `applied_at` |
| `campaigns` | Giving campaigns. | `tenant_id`, `uuid`, `name`, `goal_amount`, `start_at`, `end_at`, `status` |
| `expenses` | Expense records. | `tenant_id`, `uuid`, `department_id`, `category_id`, `amount`, `currency`, `incurred_at`, `status` |
| `expense_approvals` | Multi-step approval workflow. | `expense_id`, `step_order`, `approver_id`, `status`, `acted_at`, `comments` |
| `departments` | Ministry/department definitions. | `tenant_id`, `name`, `budget`, `leader_id` |
| `budgets` | Budget vs actual tracking. | `tenant_id`, `department_id`, `fiscal_year`, `allocated_amount`, `notes` |
| `attachments` | Receipts or documents (polymorphic). | `tenant_id`, `attachable_type`, `attachable_id`, `path`, `label` |
| `financial_reports` | Cached report snapshots. | `tenant_id`, `report_type`, `filters`, `generated_by`, `generated_at`, `file_path` |

### Volunteer Management
| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `volunteer_roles` | Opportunities. | `tenant_id`, `uuid`, `title`, `ministry_id`, `description`, `requirements`, `status` |
| `volunteer_signups` | Applications to serve. | `tenant_id`, `role_id`, `member_id`, `status`, `applied_at`, `notes` |
| `volunteer_schedules` | Assigned shifts. | `tenant_id`, `role_id`, `member_id`, `event_id`, `start_at`, `end_at`, `hours` |
| `volunteer_hours` | Logged hours for reporting. | `tenant_id`, `member_id`, `date`, `hours`, `source` |

## 3. Row-Level Security Implementation in MySQL
1. **Global Scope Enforcement:** All Eloquent models inherit `TenantScopedModel` applying `where tenant_id = ?` automatically.
2. **SQL MODE + DEFINER Views:**
   - Create views per reporting table (`CREATE VIEW v_members_tenant AS SELECT ... WHERE tenant_id = current_tenant()`).
   - Application uses stored function `current_tenant()` binding to a MySQL user session variable set on connection (`SET @tenant_id = ?`).
3. **Stored Procedures:** All write operations exposed through stored procedures validate the session tenant:
   ```sql
   IF tenant_arg <> current_tenant() THEN
     SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized tenant access';
   END IF;
   ```
4. **Database Accounts:** Separate MySQL users per tenant-facing service role (API, analytics). Analytics user restricted to views only.
5. **Migration Guardrails:** Database migrations include automated check ensuring every new table contains `tenant_id` unless explicitly opted out (system tables).

## 4. Performance & Indexing
- Composite indexes: `(tenant_id, foreign_key)` must exist on every FK column.
- Time-based queries: add `(tenant_id, recorded_at)` indexes for attendance, donations, notifications.
- Full-text search: Use MySQL InnoDB full-text on `members` (names, notes) or integrate Meilisearch/Elastic for advanced search.
- Partitioning: Strategic partitioning on `attendance_records` and `ledger_entries` by year for high-volume tenants.

## 5. Data Lifecycle & Compliance
- **Backups:** RDS automated snapshots; custom job exports tenant data to encrypted S3 for compliance.
- **Retention Policies:** Soft delete + scheduled hard delete after configurable period per tenant.
- **Auditing:** `audit_logs` table capturing `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `payload`, `ip`, `user_agent`, `logged_at`.
- **PII Protection:** Mask sensitive fields in read replicas; encryption via Laravel `EncryptedCast` for fields like SSN, giving amounts when needed.

