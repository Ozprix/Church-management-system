<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_service(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'name' => 'Sunday Celebration',
            'description' => 'Main worship gathering',
            'default_location' => 'Main Auditorium',
            'default_start_time' => '09:30',
            'default_duration_minutes' => 120,
            'absence_threshold' => 3,
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/services', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('services', [
            'tenant_id' => $tenant->id,
            'name' => 'Sunday Celebration',
        ]);
    }

    public function test_it_updates_service_with_unique_constraints(): void
    {
        $tenant = Tenant::factory()->has(Service::factory()->state([
            'slug' => 'sunday-celebration',
            'short_code' => 'SUN1',
        ]))->create();

        $this->actingAsTenantAdmin($tenant);

        /** @var Service $service */
        $service = $tenant->services()->first();

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->patchJson("/api/v1/services/{$service->id}", [
                'name' => 'Midweek Gathering',
                'short_code' => 'MID1',
            ]);

        $response->assertOk()->assertJsonPath('data.name', 'Midweek Gathering');
        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'short_code' => 'MID1',
        ]);
    }
}
