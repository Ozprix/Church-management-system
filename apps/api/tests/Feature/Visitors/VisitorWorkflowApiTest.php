<?php

declare(strict_types=1);

namespace Tests\Feature\Visitors;

use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Models\VisitorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_workflow_with_steps(): void
    {
        $tenant = Tenant::factory()->create();
        $template = NotificationTemplate::factory()->create(['tenant_id' => $tenant->id, 'channel' => 'email']);

        $this->actingAsTenantAdmin($tenant);

        $workflowResponse = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/visitor-workflows', [
                'name' => 'Visitor Welcome',
                'description' => 'Automated onboarding workflow',
            ]);

        $workflowResponse->assertCreated();
        $workflowId = $workflowResponse->json('data.id');

        $stepResponse = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson("/api/v1/visitor-workflows/{$workflowId}/steps", [
                'step_number' => 1,
                'name' => 'Send welcome email',
                'delay_minutes' => 0,
                'channel' => 'email',
                'notification_template_id' => $template->id,
            ]);

        $stepResponse->assertCreated();

        $this->assertDatabaseHas('visitor_workflow_steps', [
            'workflow_id' => $workflowId,
            'step_number' => 1,
            'channel' => 'email',
        ]);
    }
}
