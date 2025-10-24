# Volunteer Pipeline (Phase 2 TODO)

## Current State
- Service layer (`VolunteerPipelineService`) handles signups, status transitions, assignments, and hour logging.
- API endpoints exist for signups (`VolunteerSignupController`) and volunteer hours but UI/automation is minimal.
- Feature flag: `volunteer_pipeline` with permission `volunteer_pipeline.manage_signups`.
- No dedicated front-end workflow for reviewing/approving signups or managing stages.

## Pending Work
1. **Workflow states (Done)**
   - Stage progression and history logging now implemented (`VolunteerSignupStage`).

2. **UI (In progress)**
   - Volunteer pipeline overview page exists but still needs richer analytics (conversion rates, background check uploads) and bulk actions.

3. **Notifications & reminders**
   - Initial coordinator + volunteer emails added. Follow-up reminder scheduling still pending.

4. **Automations**
   - Automatic assignments on “ready” stage implemented. Remaining todo: background check integrations & plan usage audits.

5. **Tests & analytics**
   - Feature tests cover stage transitions + analytics counts. Still need end-to-end browser coverage and reporting dashboards.

## Resources
- `apps/api/app/Services/VolunteerPipelineService.php`
- `apps/api/app/Http/Controllers/Api/Volunteer`
- `apps/api/tests/Feature/Volunteers/VolunteerPipelineApiTest.php`
- `apps/web/app/volunteers/...` (existing volunteer management UI components)
