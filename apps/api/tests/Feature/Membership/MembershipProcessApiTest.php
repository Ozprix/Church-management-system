<?php

declare(strict_types=1);

namespace Tests\Feature\Membership;

use App\Jobs\SendMembershipStageReminderJob;
use App\Models\Member;
use App\Models\MemberProcessRun;
use App\Models\MembershipProcess;
use App\Models\MembershipProcessStage;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Services\MembershipProcessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MembershipProcessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_process_with_stages(): void
    {
        $tenantUser = $this->actingAsTenantAdmin();
        $payload = [
            'name' => 'Onboarding',
            'slug' => 'onboarding',
            'is_active' => true,
            'stages' => [
                ['name' => 'Welcome Call'],
                ['name' => 'Membership Class'],
            ],
        ];

        $headerTenant = (string) (optional($tenantUser->tenant)->uuid ?? $tenantUser->tenant_id);

        $response = $this
            ->withHeader('X-Tenant-ID', $headerTenant)
            ->postJson('/api/v1/membership-processes', $payload);

        $response->assertCreated()->assertJsonPath('data.stages.0.name', 'Welcome Call');

        $this->assertDatabaseHas('membership_processes', [
            'slug' => 'onboarding',
        ]);

        $this->assertDatabaseHas('membership_process_stages', [
            'name' => 'Membership Class',
        ]);
    }

    public function test_member_creation_auto_starts_process(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser($tenant, roles: ['admin']);

        $process = MembershipProcess::factory()->create([
            'tenant_id' => $tenant->id,
            'auto_start_on_member_create' => true,
        ]);

        $process->stages()->create([
            'key' => 'welcome',
            'name' => 'Welcome Stage',
            'step_order' => 1,
        ]);

        $payload = [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'membership_status' => 'active',
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/members', $payload);

        $response->assertCreated();

        $member = Member::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertDatabaseHas('member_process_runs', [
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'process_id' => $process->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_stage_entry_actions_trigger_notifications_and_reminders(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser($tenant, roles: ['admin']);

        $template = NotificationTemplate::factory()->email()->create([
            'tenant_id' => $tenant->id,
        ]);

        $payload = [
            'name' => 'Assimilation',
            'slug' => 'assimilation',
            'stages' => [
                [
                    'name' => 'Welcome Email',
                    'entry_actions' => [
                        ['type' => 'notify_member', 'template_id' => $template->id, 'channel' => 'email'],
                    ],
                    'reminder_minutes' => 60,
                    'reminder_template_id' => $template->id,
                ],
            ],
        ];

        $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/membership-processes', $payload)
            ->assertCreated();

        $process = MembershipProcess::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $member = Member::factory()->create(['tenant_id' => $tenant->id]);

        $run = app(MembershipProcessService::class)->startProcess($member, $process);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'notification_template_id' => $template->id,
        ]);

        Queue::assertPushed(SendMembershipStageReminderJob::class, function (SendMembershipStageReminderJob $job) use ($run) {
            return $job->getRunId() === $run->id;
        });
    }

    public function test_process_report_returns_stage_counts(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser($tenant, roles: ['admin']);

        $process = MembershipProcess::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $stageA = MembershipProcessStage::factory()->create([
            'process_id' => $process->id,
            'step_order' => 1,
            'name' => 'Step A',
        ]);

        $stageB = MembershipProcessStage::factory()->create([
            'process_id' => $process->id,
            'step_order' => 2,
            'name' => 'Step B',
        ]);

        foreach (range(1, 2) as $i) {
            $member = Member::factory()->create(['tenant_id' => $tenant->id]);
            MemberProcessRun::factory()->create([
                'tenant_id' => $tenant->id,
                'process_id' => $process->id,
                'member_id' => $member->id,
                'current_stage_id' => $stageA->id,
                'status' => 'in_progress',
            ]);
        }

        $memberStageB = Member::factory()->create(['tenant_id' => $tenant->id]);
        MemberProcessRun::factory()->create([
            'tenant_id' => $tenant->id,
            'process_id' => $process->id,
            'member_id' => $memberStageB->id,
            'current_stage_id' => $stageB->id,
            'status' => 'in_progress',
        ]);

        $memberCompleted = Member::factory()->create(['tenant_id' => $tenant->id]);
        MemberProcessRun::factory()->create([
            'tenant_id' => $tenant->id,
            'process_id' => $process->id,
            'member_id' => $memberCompleted->id,
            'current_stage_id' => null,
            'status' => 'completed',
            'started_at' => now()->subHours(5),
            'completed_at' => now(),
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->getJson("/api/v1/membership-processes/{$process->id}/report");

        $response->assertOk()
            ->assertJsonPath('data.total_runs', 4)
            ->assertJsonPath('data.stage_counts.0.stage_name', 'Step A')
            ->assertJsonPath('data.status_counts.in_progress', 3);
    }
}
