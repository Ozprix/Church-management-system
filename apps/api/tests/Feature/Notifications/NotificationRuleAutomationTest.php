<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Member;
use App\Models\NotificationRule;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationRuleAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_runs_notification_rule(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser($tenant, roles: ['communications_manager']);

        $template = NotificationTemplate::factory()->email()->create([
            'tenant_id' => $tenant->id,
        ]);

        $payload = [
            'tenant_id' => $tenant->id,
            'name' => 'Birthday Greetings',
            'slug' => 'birthday-greetings',
            'trigger_type' => 'member_status',
            'trigger_config' => ['member_status' => 'active'],
            'channel' => 'email',
            'notification_template_id' => $template->id,
            'status' => 'active',
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/notification-rules', $payload);

        $response->assertCreated()->assertJsonPath('data.slug', 'birthday-greetings');

        Member::factory()->create([
            'tenant_id' => $tenant->id,
            'membership_status' => 'active',
        ]);

        Queue::fake();

        $ruleId = $response->json('data.id');

        $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson("/api/v1/notification-rules/{$ruleId}/run")
            ->assertOk();

        $this->assertDatabaseHas('notification_rule_runs', [
            'notification_rule_id' => $ruleId,
            'status' => 'completed',
        ]);
    }
}
