<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Gathering;
use App\Models\Member;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\VisitorFollowup;
use App\Models\VisitorWorkflow;
use App\Models\VisitorWorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_absence_threshold_triggers_followup_and_notification(): void
    {
        [$tenant, $service, $workflow, $members, $gatherings] = $this->setupAbsenceScenario();

        $member = $members['absent'];

        $this->actingAsTenantAdmin($tenant);

        foreach ($gatherings as $gathering) {
            $this
                ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
                ->postJson("/api/v1/gatherings/{$gathering->uuid}/attendance", [
                    'member_id' => $member->id,
                    'status' => 'absent',
                ])
                ->assertCreated();
        }

        $followup = VisitorFollowup::query()
            ->where('tenant_id', $tenant->id)
            ->where('member_id', $member->id)
            ->where('workflow_id', $workflow->id)
            ->where('metadata->trigger', 'attendance_absence')
            ->first();

        $this->assertNotNull($followup);
        $this->assertContains($followup->status, ['pending', 'in_progress']);

        $this->assertDatabaseCount('notifications', 1);

        // Additional absence should not create duplicate follow-ups
        $extraGathering = Gathering::factory()->create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'status' => 'completed',
            'starts_at' => now()->addDays(1),
        ]);

        $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson("/api/v1/gatherings/{$extraGathering->uuid}/attendance", [
                'member_id' => $member->id,
                'status' => 'absent',
            ])
            ->assertCreated();

        $this->assertSame(VisitorFollowup::query()->where('metadata->trigger', 'attendance_absence')->count(), 1);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_presence_resolves_absence_followup(): void
    {
        [$tenant, $service, $workflow, $members, $gatherings] = $this->setupAbsenceScenario();

        $member = $members['absent'];

        $this->actingAsTenantAdmin($tenant);

        foreach ($gatherings as $gathering) {
            $this
                ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
                ->postJson("/api/v1/gatherings/{$gathering->uuid}/attendance", [
                    'member_id' => $member->id,
                    'status' => 'absent',
                ]);
        }

        $followup = VisitorFollowup::query()
            ->where('metadata->trigger', 'attendance_absence')
            ->where('tenant_id', $tenant->id)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $targetRecord = AttendanceRecord::query()->where('member_id', $member->id)->firstOrFail();

        $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson("/api/v1/gatherings/{$gatherings[0]->uuid}/attendance", [
                'member_id' => $member->id,
                'status' => 'present',
                'check_in_method' => 'kiosk_offline',
            ])
            ->assertCreated();

        $followup->refresh();
        $this->assertSame('completed', $followup->status);
        $this->assertNotNull($followup->completed_at);
        $this->assertSame($targetRecord->member_id, $member->id);
    }

    /**
     * @return array{Tenant, Service, VisitorWorkflow, array{absent: Member}, array<int, Gathering>}
     */
    private function setupAbsenceScenario(): array
    {
        $tenant = Tenant::factory()->create();
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'absence_threshold' => 2,
        ]);

        $workflow = VisitorWorkflow::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        VisitorWorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'step_number' => 1,
            'delay_minutes' => 60,
            'channel' => 'task',
        ]);

        $absentMember = Member::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $gatherings = Gathering::factory()
            ->count(2)
            ->create([
                'tenant_id' => $tenant->id,
                'service_id' => $service->id,
                'status' => 'completed',
            ]);

        return [$tenant, $service, $workflow, ['absent' => $absentMember], $gatherings];
    }
}
