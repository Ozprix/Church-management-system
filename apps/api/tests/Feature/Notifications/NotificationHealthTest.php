<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Notification;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_channel_health_with_provider_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser($tenant, roles: ['communications_manager']);

        Notification::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'channel' => 'sms',
            'status' => 'sent',
        ]);

        Notification::factory()->create([
            'tenant_id' => $tenant->id,
            'channel' => 'sms',
            'status' => 'failed',
            'error_message' => 'Twilio rejected number',
        ]);

        Notification::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'channel' => 'email',
            'status' => 'sent',
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/notifications/health');

        $response->assertOk()->assertJsonCount(2, 'data');

        $payload = collect($response->json('data'));
        $sms = $payload->firstWhere('channel', 'sms');

        $this->assertSame('degraded', $sms['health']);
        $this->assertSame(3, $sms['totals']['sent']);
        $this->assertSame(1, $sms['totals']['failed']);
        $this->assertSame('Twilio', $sms['provider']['name']);
        $this->assertFalse($sms['provider']['configured']);

        $email = $payload->firstWhere('channel', 'email');
        $this->assertSame('healthy', $email['health']);
        $this->assertSame(2, $email['totals']['sent']);
        $this->assertSame('Mailgun', $email['provider']['name']);
    }
}
