<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Jobs\SendNotificationJob;
use App\Models\AttendanceAnalyticsReport;
use App\Models\Notification as NotificationModel;
use App\Models\AttendanceRecord;
use App\Models\Gathering;
use App\Models\Member;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceAnalyticsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_updates_and_lists_reports(): void
    {
        [$tenant] = $this->seedAttendanceData();

        $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/attendance-analytics-reports', [
                'name' => 'Weekend summary',
                'filters' => [
                    'status' => 'completed',
                ],
                'frequency' => 'weekly',
                'channel' => 'email',
                'email_recipient' => 'pastor@example.com',
            ]);

        $response->assertCreated();

        $reportId = $response->json('data.id');
        $this->assertNotNull($reportId);

        $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->putJson("/api/v1/attendance-analytics-reports/{$reportId}", [
                'name' => 'Updated name',
                'frequency' => 'monthly',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated name')
            ->assertJsonPath('data.frequency', 'monthly');

        $indexResponse = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->getJson('/api/v1/attendance-analytics-reports');

        $indexResponse->assertOk();
        $this->assertCount(1, $indexResponse->json('data'));
    }

    public function test_it_runs_and_exports_report(): void
    {
        [$tenant, $gathering] = $this->seedAttendanceData();

        $report = AttendanceAnalyticsReport::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Weekend summary',
            'user_id' => null,
            'filters' => [
                'service_id' => $gathering->service_id,
            ],
            'frequency' => 'weekly',
            'channel' => 'email',
        ]);

        $this->actingAsTenantAdmin($tenant);

        $runResponse = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson("/api/v1/attendance-analytics-reports/{$report->id}/run");

        $runResponse->assertOk();
        $this->assertSame(
            $gathering->name,
            $runResponse->json('top_gatherings.0.name')
        );

        $exportResponse = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->get("/api/v1/attendance-analytics-reports/{$report->id}/export");

        $exportResponse->assertOk();
        $this->assertStringContainsString('text/csv', (string) $exportResponse->headers->get('content-type'));
        $this->assertStringContainsString($gathering->name, $exportResponse->streamedContent());
    }

    public function test_it_deletes_report(): void
    {
        [$tenant] = $this->seedAttendanceData();

        $report = AttendanceAnalyticsReport::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'To delete',
            'user_id' => null,
        ]);

        $this->actingAsTenantAdmin($tenant);

        $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->deleteJson("/api/v1/attendance-analytics-reports/{$report->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('attendance_analytics_reports', [
            'id' => $report->id,
        ]);
    }

    public function test_scheduled_download_report_persists_snapshot_and_allows_download(): void
    {
        [$tenant] = $this->seedAttendanceData();

        Storage::fake('reports');

        $report = AttendanceAnalyticsReport::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Download snapshot report',
            'user_id' => null,
            'frequency' => 'daily',
            'channel' => 'download',
            'last_run_at' => now()->subDays(2),
        ]);

        $this->artisan('attendance:run-saved-reports')->assertExitCode(0);

        $report->refresh();

        $this->assertNotNull($report->last_run_at);
        $this->assertGreaterThanOrEqual(1, $report->snapshots()->count());

        $snapshot = $report->snapshots()->latest('generated_at')->first();
        $this->assertNotNull($snapshot);
        Storage::disk('reports')->assertExists($snapshot->file_path);

        $this->actingAsTenantAdmin($tenant);

        $listResponse = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->getJson("/api/v1/attendance-analytics-reports/{$report->id}/snapshots");

        $listResponse->assertOk();
        $this->assertCount(1, $listResponse->json('data'));

        $downloadResponse = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->get("/api/v1/attendance-analytics-reports/{$report->id}/snapshots/{$snapshot->id}");

        $downloadResponse->assertOk();
        $this->assertStringContainsString('text/csv', (string) $downloadResponse->headers->get('content-type'));
    }

    public function test_scheduled_both_channel_report_dispatches_notification_and_snapshot(): void
    {
        [$tenant] = $this->seedAttendanceData();

        Storage::fake('reports');
        Bus::fake();

        $report = AttendanceAnalyticsReport::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Email and download report',
            'user_id' => null,
            'frequency' => 'weekly',
            'channel' => 'both',
            'email_recipient' => 'reports@example.com',
            'last_run_at' => now()->subWeeks(2),
        ]);

        $this->artisan('attendance:run-saved-reports')->assertExitCode(0);

        $report->refresh();

        $notifications = NotificationModel::query()
            ->where('tenant_id', $tenant->id)
            ->get();

        $this->assertCount(1, $notifications);
        $notification = $notifications->first();
        $this->assertSame('email', $notification->channel);
        $this->assertSame('queued', $notification->status);
        $this->assertStringContainsString('Scheduled attendance analytics report', $notification->subject);

        Bus::assertDispatched(SendNotificationJob::class, function (SendNotificationJob $job) use ($notification) {
            return $job->notificationId === $notification->id;
        });

        $this->assertGreaterThanOrEqual(1, $report->snapshots()->count());
        $snapshot = $report->snapshots()->latest('generated_at')->first();
        $this->assertNotNull($snapshot);
        Storage::disk('reports')->assertExists($snapshot->file_path);
    }

    /**
     * @return array{Tenant, Gathering}
     */
    private function seedAttendanceData(): array
    {
        $tenant = Tenant::factory()->create();
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        /** @var Gathering $gathering */
        $gathering = Gathering::factory()->create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'status' => 'completed',
            'starts_at' => now()->subDay()->startOfHour(),
        ]);

        $member = Member::factory()->create(['tenant_id' => $tenant->id]);

        AttendanceRecord::factory()
            ->forGathering($gathering)
            ->forMember($member)
            ->state([
                'status' => 'present',
                'checked_in_at' => now()->subDay()->startOfHour(),
            ])
            ->create();

        return [$tenant, $gathering];
    }
}
