<?php

declare(strict_types=1);

namespace Tests\Feature\Volunteers;

use App\Models\Member;
use App\Models\Tenant;
use App\Models\VolunteerAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolunteerAvailabilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_availability(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $availability = VolunteerAvailability::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
        ]);

        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'weekdays' => ['saturday', 'sunday'],
            'time_blocks' => [
                ['start' => '06:00', 'end' => '09:00'],
            ],
            'notes' => 'Prefer early services only.',
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->putJson("/api/v1/volunteer-availability/{$availability->id}", $payload);

        $response->assertOk()->assertJsonPath('data.weekdays.0', 'saturday');
        $this->assertDatabaseHas('volunteer_availability', [
            'id' => $availability->id,
            'notes' => 'Prefer early services only.',
        ]);
    }
}
