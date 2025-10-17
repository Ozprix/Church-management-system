<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationTemplate;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_template(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'name' => 'Welcome Email',
            'slug' => 'welcome-email',
            'channel' => 'email',
            'subject' => 'Welcome {{name}}',
            'body' => 'Hello {{name}}, welcome to {{church}}.',
            'placeholders' => ['name', 'church'],
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/notification-templates', $payload);

        $response->assertCreated()->assertJsonPath('data.slug', 'welcome-email');
        $this->assertDatabaseHas('notification_templates', [
            'tenant_id' => $tenant->id,
            'slug' => 'welcome-email',
        ]);
    }

    public function test_it_updates_template(): void
    {
        $template = NotificationTemplate::factory()->email()->create();
        $tenant = $template->tenant;

        $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->patchJson("/api/v1/notification-templates/{$template->id}", [
                'name' => 'Updated Template',
                'subject' => 'Updated {{name}}',
            ]);

        $response->assertOk()->assertJsonPath('data.name', 'Updated Template');
        $this->assertDatabaseHas('notification_templates', [
            'id' => $template->id,
            'name' => 'Updated Template',
        ]);
    }
}
