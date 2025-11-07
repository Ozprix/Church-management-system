<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Models\Gathering;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GatheringTicketingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_types_and_registrations_enforce_capacity(): void
    {
        Carbon::setTestNow('2025-05-01 08:00:00');

        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $gathering = Gathering::factory()->create([
            'tenant_id' => $tenant->id,
            'starts_at' => Carbon::parse('2025-05-10 10:00'),
            'ends_at' => Carbon::parse('2025-05-10 12:00'),
            'location' => 'Auditorium',
        ]);

        $ticketResponse = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson("/api/v1/gatherings/{$gathering->uuid}/ticket-types", [
                'name' => 'General Admission',
                'capacity' => 3,
                'price' => 25,
            ]);

        $ticketResponse->assertCreated();
        $ticketId = $ticketResponse->json('data.id');

        $registrationResponse = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson("/api/v1/gatherings/{$gathering->uuid}/registrations", [
                'ticket_type_id' => $ticketId,
                'quantity' => 2,
                'name' => 'Alex Doe',
                'email' => 'alex@example.test',
                'status' => 'confirmed',
            ]);

        $registrationResponse->assertCreated();

        $overCapacity = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson("/api/v1/gatherings/{$gathering->uuid}/registrations", [
                'ticket_type_id' => $ticketId,
                'quantity' => 2,
                'name' => 'Jamie Doe',
                'email' => 'jamie@example.test',
            ]);

        $overCapacity->assertStatus(422);
        $this->assertStringContainsString('Only 1 tickets remaining', $overCapacity->json('errors.quantity.0'));
    }

    public function test_registration_check_in_updates_status(): void
    {
        Carbon::setTestNow('2025-05-01 08:00:00');

        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $gathering = Gathering::factory()->create([
            'tenant_id' => $tenant->id,
            'starts_at' => Carbon::parse('2025-05-10 10:00'),
            'ends_at' => Carbon::parse('2025-05-10 12:00'),
        ]);

        $ticketId = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson("/api/v1/gatherings/{$gathering->uuid}/ticket-types", [
                'name' => 'VIP',
                'capacity' => 10,
                'price' => 50,
            ])->json('data.id');

        $registrationId = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson("/api/v1/gatherings/{$gathering->uuid}/registrations", [
                'ticket_type_id' => $ticketId,
                'quantity' => 1,
                'name' => 'Taylor Smith',
                'email' => 'taylor@example.test',
            ])->json('data.id');

        $checkIn = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson("/api/v1/gatherings/{$gathering->uuid}/registrations/{$registrationId}/check-in");

        $checkIn->assertOk();
        $this->assertSame('checked_in', $checkIn->json('data.status'));
        $this->assertNotNull($checkIn->json('data.checked_in_at'));
    }
}
