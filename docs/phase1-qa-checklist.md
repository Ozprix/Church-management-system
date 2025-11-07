# Phase 1 QA Checklist

Use this checklist when validating the Member & Family Core release. Record outcomes in your test run notes and file defects against the `phase-1` label.

## 1. Test Preparation
- [ ] Set up a fresh tenant with seeded members, families, and visitor workflows.
- [ ] Ensure `.env` values allow document uploads (local storage path or S3 bucket configured).
- [ ] Log in with both admin and pastoral staff accounts to confirm role-based access.

## 2. Member Management
- [ ] Create a new member (verify required fields, contact validation, error toasts).
- [ ] Upload at least two documents via the "Household documents" card (verify allowed file types, file size limits, and download links).
- [ ] Edit an existing member’s structured custom fields and confirm values persist after save.

## 3. Families & Analytics
- [ ] Assign a member to an existing family and mark primary/emergency contacts.
- [ ] Visit `/families` and `/families/analytics` to confirm dashboard stats reflect the changes (follow-up reminders, distribution charts).
- [ ] Export the families CSV and verify contents include the updated household.

## 4. Attendance & Kiosk
- [ ] Record attendance for a gathering and confirm entries appear on member detail → Attendance tab.
- [ ] Load `/attendance/kiosk`, perform an offline check-in, then reconnect and ensure the sync queue flushes.

## 5. Visitor Automation
- [ ] Configure a workflow step with rule builder conditions (e.g., `member_status` is visitor and `visit_count` greater than 1).
- [ ] Start a follow-up for a member that meets the conditions and verify the step runs and the log status is `queued`/`sent`.
- [ ] Start a follow-up for a member that does *not* meet the conditions and confirm the step is skipped with a descriptive log entry.

## 6. Regression Smoke
- [ ] Members analytics exports (`/members/analytics`) download successfully.
- [ ] Custom field admin form enforces extension/MIME/max-size validation.
- [ ] Global navigation links (Members, Families, Visitors, Finance) work for admin and pastoral roles.

## 7. Sign-off
- [ ] Document all pass/fail results in the QA log.
- [ ] File tickets for any defects or UX concerns under the Phase 1 polish milestone.
