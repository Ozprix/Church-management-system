<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\FamilyController;
use App\Http\Controllers\Api\FamilyDashboardController;
use App\Http\Controllers\Api\FamilyExportController;
use App\Http\Controllers\Api\Finance\DashboardController;
use App\Http\Controllers\Api\Finance\DonationController;
use App\Http\Controllers\Api\Finance\FinanceExportController;
use App\Http\Controllers\Api\Finance\FundController;
use App\Http\Controllers\Api\Finance\PaymentMethodController;
use App\Http\Controllers\Api\Finance\PledgeController;
use App\Http\Controllers\Api\Finance\RecurringDonationAttemptController;
use App\Http\Controllers\Api\Finance\RecurringDonationScheduleController;
use App\Http\Controllers\Api\GatheringController;
use App\Http\Controllers\Api\MemberAnalyticsController;
use App\Http\Controllers\Api\MemberAnalyticsExportController;
use App\Http\Controllers\Api\MemberAnalyticsReportController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\MemberAuditController;
use App\Http\Controllers\Api\MemberImportController;
use App\Http\Controllers\Api\MemberCustomFieldController;
use App\Http\Controllers\Api\MemberCustomFieldUploadController;
use App\Http\Controllers\Api\MemberCustomValueFileController;
use App\Http\Controllers\Api\MemberExportController;
use App\Http\Controllers\Api\FamilyAnalyticsController;
use App\Http\Controllers\Api\FamilyAnalyticsExportController;
use App\Http\Controllers\Api\Membership\MemberProcessRunController;
use App\Http\Controllers\Api\Membership\MembershipProcessController;
use App\Http\Controllers\Api\Membership\MembershipProcessReportController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationRuleController;
use App\Http\Controllers\Api\NotificationTemplateController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\FinanceAnalyticsController;
use App\Http\Controllers\Api\FinanceAnalyticsExportController;
use App\Http\Controllers\Api\Tenant\TenantController;
use App\Http\Controllers\Api\Tenant\TenantDomainController;
use App\Http\Controllers\Api\VisitorFollowupController;
use App\Http\Controllers\Api\VisitorWorkflowController;
use App\Http\Controllers\Api\VisitorWorkflowStepController;
use App\Http\Controllers\Api\UserTwoFactorAdminController;
use App\Http\Controllers\Api\Volunteer\VolunteerAnalyticsController;
use App\Http\Controllers\Api\Volunteer\VolunteerAssignmentController;
use App\Http\Controllers\Api\Volunteer\VolunteerAvailabilityController;
use App\Http\Controllers\Api\Volunteer\VolunteerHourController;
use App\Http\Controllers\Api\Volunteer\VolunteerRoleController;
use App\Http\Controllers\Api\Volunteer\VolunteerSignupController;
use App\Http\Controllers\Api\Volunteer\VolunteerTeamController;
use App\Http\Controllers\Api\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

    Route::get('plans', [TenantController::class, 'plans'])
        ->defaults('allow_without_tenant', true)
        ->withoutMiddleware(\App\Http\Middleware\ResolveTenant::class);

    Route::post('tenants', [TenantController::class, 'store'])
        ->defaults('allow_without_tenant', true)
        ->withoutMiddleware(\App\Http\Middleware\ResolveTenant::class);

    Route::post('tenants/{tenant}/domains/{tenantDomain}/verify', [TenantController::class, 'verifyDomain'])
        ->defaults('allow_without_tenant', true)
        ->withoutMiddleware(\App\Http\Middleware\ResolveTenant::class)
        ->withoutMiddleware('auth:sanctum');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/two-factor/setup', [AuthController::class, 'setupTwoFactor'])->name('auth.2fa.setup');
        Route::post('auth/two-factor/confirm', [AuthController::class, 'confirmTwoFactor'])->name('auth.2fa.confirm');
        Route::post('auth/two-factor/recovery-codes', [AuthController::class, 'regenerateRecoveryCodes'])->name('auth.2fa.recovery');
        Route::delete('auth/two-factor', [AuthController::class, 'disableTwoFactor'])->name('auth.2fa.disable');
        Route::post('auth/two-factor/admin-reset', [UserTwoFactorAdminController::class, 'reset'])
            ->name('auth.2fa.admin-reset')
            ->middleware('can:users.manage_security');

        Route::get('member-imports', [MemberImportController::class, 'index'])
            ->name('member-imports.index');
        Route::post('member-imports', [MemberImportController::class, 'store'])
            ->middleware('throttle:member-import-upload')
            ->name('member-imports.store');
        Route::get('member-imports/{memberImport}', [MemberImportController::class, 'show'])
            ->name('member-imports.show');

        Route::post('members/bulk-import', [MemberController::class, 'bulkImport'])
            ->middleware('throttle:member-bulk-operations')
            ->name('members.bulk-import');
        Route::post('members/bulk-delete', [MemberController::class, 'bulkDelete'])
            ->middleware('throttle:member-bulk-operations')
            ->name('members.bulk-delete');
        Route::post('members/{member}/restore', [MemberController::class, 'restore'])->name('members.restore');
        Route::get('members/{member}/audits', [MemberAuditController::class, 'index'])->name('members.audits.index');
        Route::apiResource('members', MemberController::class);
        Route::get('members/analytics', MemberAnalyticsController::class);
        Route::get('members/analytics/export', MemberAnalyticsExportController::class);
        Route::get('families/analytics', FamilyAnalyticsController::class);
        Route::get('families/analytics/export', FamilyAnalyticsExportController::class);
        Route::apiResource('member-analytics-reports', MemberAnalyticsReportController::class);
        Route::post('member-analytics-reports/{memberAnalyticsReport}/run', [MemberAnalyticsReportController::class, 'run']);
        Route::get('member-analytics-reports/{memberAnalyticsReport}/export', [MemberAnalyticsReportController::class, 'export']);
        Route::get('members/export', MemberExportController::class);
        Route::apiResource('families', FamilyController::class);
        Route::get('families/export', FamilyExportController::class);
        Route::get('families/dashboard', FamilyDashboardController::class);
        Route::post('tenants/{tenant}/plans', [TenantController::class, 'assignPlan']);
        Route::get('finance/analytics', FinanceAnalyticsController::class);
        Route::get('finance/analytics/export', FinanceAnalyticsExportController::class);

        Route::get('tenant/profile', [TenantController::class, 'profile']);
        Route::get('tenant/domains', [TenantDomainController::class, 'index']);
        Route::post('tenant/domains', [TenantDomainController::class, 'store']);
        Route::delete('tenant/domains/{tenantDomain}', [TenantDomainController::class, 'destroy']);
        Route::post('tenant/domains/{tenantDomain}/regenerate', [TenantDomainController::class, 'regenerate']);

        Route::apiResource('membership-processes', MembershipProcessController::class);
        Route::get('member-process-runs', [MemberProcessRunController::class, 'index']);
        Route::post('membership-processes/{membership_process}/runs', [MemberProcessRunController::class, 'store']);
        Route::post('member-process-runs/{member_process_run}/advance', [MemberProcessRunController::class, 'advance']);
        Route::post('member-process-runs/{member_process_run}/halt', [MemberProcessRunController::class, 'halt']);
        Route::get('membership-processes/{membership_process}/report', [MembershipProcessReportController::class, 'show']);

        Route::apiResource('member-custom-fields', MemberCustomFieldController::class);
        Route::post('member-custom-fields/{memberCustomField}/uploads', [MemberCustomFieldUploadController::class, 'store']);
        Route::get('member-custom-values/{memberCustomValue}/file', MemberCustomValueFileController::class)
            ->name('member-custom-values.file.download');

        Route::apiResource('visitor-workflows', VisitorWorkflowController::class);
        Route::apiResource('visitor-workflows.steps', VisitorWorkflowStepController::class)
            ->shallow()
            ->only(['store', 'update', 'destroy']);
        Route::apiResource('visitor-followups', VisitorFollowupController::class)->only(['index', 'store', 'update']);

        Route::apiResource('services', ServiceController::class);
        Route::apiResource('gatherings', GatheringController::class);

        Route::apiResource('notification-templates', NotificationTemplateController::class);
        Route::apiResource('notifications', NotificationController::class);
        Route::apiResource('notification-rules', NotificationRuleController::class);
        Route::post('notification-rules/{notification_rule}/run', [NotificationRuleController::class, 'run']);

        Route::apiResource('volunteer-roles', VolunteerRoleController::class);
        Route::apiResource('volunteer-teams', VolunteerTeamController::class);
        Route::apiResource('volunteer-assignments', VolunteerAssignmentController::class);
        Route::post('volunteer-assignments/{volunteer_assignment}/swap', [VolunteerAssignmentController::class, 'swap']);
        Route::apiResource('volunteer-availability', VolunteerAvailabilityController::class)->only(['index', 'store', 'update']);
        Route::apiResource('volunteer-signups', VolunteerSignupController::class);
        Route::apiResource('volunteer-hours', VolunteerHourController::class)->only(['index', 'store']);
        Route::get('volunteer/analytics/summary', [VolunteerAnalyticsController::class, 'summary']);

        Route::apiResource('funds', FundController::class);
        Route::apiResource('pledges', PledgeController::class);
        Route::apiResource('donations', DonationController::class);
        Route::apiResource('payment-methods', PaymentMethodController::class);
        Route::apiResource('recurring-donations', RecurringDonationScheduleController::class);
        Route::get('recurring-donations/{recurring_donation}/attempts', [RecurringDonationAttemptController::class, 'index']);

        Route::get('finance/reports/donations/export', [FinanceExportController::class, 'donations']);
        Route::get('finance/reports/pledges/export', [FinanceExportController::class, 'pledges']);
        Route::get('finance/reports/donor-statement/{member}', [FinanceExportController::class, 'donorStatement']);
        Route::get('finance/dashboard', DashboardController::class);

        Route::get('gatherings/{gathering}/attendance', [AttendanceController::class, 'index']);
        Route::post('gatherings/{gathering}/attendance', [AttendanceController::class, 'store']);
        Route::post('gatherings/{gathering}/attendance/bulk', [AttendanceController::class, 'bulk']);
        Route::patch('gatherings/{gathering}/attendance/{attendanceRecord}', [AttendanceController::class, 'update']);
        Route::delete('gatherings/{gathering}/attendance/{attendanceRecord}', [AttendanceController::class, 'destroy']);
    });
});

Route::post('webhooks/stripe', StripeWebhookController::class);
