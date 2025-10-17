<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_notification_and_dispatches_job(): void
    {
        $tenant = Tenant::factory()->create();
        $template = NotificationTemplate::factory()->email()->create(['tenant_id' => $tenant->id]);

        Queue::fake();

        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'notification_template_id' => $template->id,
            'channel' => 'email',
            'recipient' => 'user@example.com',
            'payload' => ['name' => 'Ada', 'church' => 'Grace Chapel'],
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/notifications', $payload);

        $response->assertCreated();
        $notificationId = $response->json('data.id');

        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'tenant_id' => $tenant->id,
            'status' => 'queued',
        ]);

        Queue::assertPushed(SendNotificationJob::class, fn (SendNotificationJob $job) => $job->notificationId === $notificationId);
    }

    public function test_job_sends_notification(): void
    {
        $notification = Notification::factory()->create([
            'channel' => 'sms',
            'recipient' => '+15550001111',
            'status' => 'queued',
        ]);

        $job = new SendNotificationJob($notification->id);

        $job->handle(app(NotificationService::class));

        $notification->refresh();

        $this->assertSame('sent', $notification->status);
        $this->assertNotNull($notification->sent_at);
        $this->assertSame('twilio', $notification->provider);
    }
}
