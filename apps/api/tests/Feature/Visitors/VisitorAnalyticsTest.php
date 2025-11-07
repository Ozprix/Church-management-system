<?php

declare(strict_types=1);

namespace Tests\Feature\Visitors;

use App\Models\Member;
use App\Models\Tenant;
use App\Models\VisitorFollowup;
use App\Models\VisitorFollowupLog;
use App\Models\VisitorWorkflow;
use App\Models\VisitorWorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_analytics_returns_metrics(): void
    {
        $tenant = Tenant::factory()->create();

        $visitor = Member::factory()->create([
            'tenant_id' => $tenant->id,
            'membership_status' => 'visitor',
        ]);

        $converted = Member::factory()->create([
            'tenant_id' => $tenant->id,
            'membership_status' => 'active',
            'joined_at' => now()->subDays(5),
        ]);

        $workflow = VisitorWorkflow::factory()->create(['tenant_id' => $tenant->id]);
        $step = VisitorWorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'step_number' => 1,
            'channel' => 'email',
        ]);

        $followup = VisitorFollowup::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $visitor->id,
            'workflow_id' => $workflow->id,
            'status' => 'in_progress',
        ]);

        VisitorFollowupLog::factory()->create([
            'followup_id' => $followup->id,
            'step_id' => $step->id,
            'status' => 'sent',
            'channel' => 'email',
            'run_at' => now()->subDay(),
        ]);

        $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/visitors/analytics');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'stats' => ['total_visitors', 'converted_visitors', 'active_followups', 'completed_last_30_days', 'conversion_rate'],
                    'breakdown' => ['followup_statuses', 'workflows'],
                    'recent_activity',
                ],
            ]);

        $payload = $response->json('data.stats');

        $this->assertSame(1, $payload['total_visitors']);
        $this->assertSame(1, $payload['converted_visitors']);
        $this->assertSame(1, $payload['active_followups']);
    }
}
