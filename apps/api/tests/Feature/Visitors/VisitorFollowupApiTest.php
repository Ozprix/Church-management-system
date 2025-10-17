<?php

declare(strict_types=1);

namespace Tests\Feature\Visitors;

use App\Jobs\ProcessVisitorFollowupJob;
use App\Models\Member;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Models\VisitorWorkflow;
use App\Models\VisitorWorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VisitorFollowupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_starts_followup_and_dispatches_job(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $workflow = VisitorWorkflow::factory()->create(['tenant_id' => $tenant->id]);
        $template = NotificationTemplate::factory()->create(['tenant_id' => $tenant->id, 'channel' => 'email']);
        VisitorWorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'step_number' => 1,
            'delay_minutes' => 0,
            'channel' => 'email',
            'notification_template_id' => $template->id,
        ]);

        $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/visitor-followups', [
                'member_id' => $member->id,
                'workflow_id' => $workflow->id,
            ]);

        $response->assertOk();

        Queue::assertPushed(ProcessVisitorFollowupJob::class);
    }
}
