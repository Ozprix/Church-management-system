<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Models\Gathering;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GatheringConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_conflict_is_blocked_for_same_location(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        Gathering::factory()->create([
            'tenant_id' => $tenant->id,
            'starts_at' => Carbon::parse('2025-05-01 09:00'),
            'ends_at' => Carbon::parse('2025-05-01 10:30'),
            'location' => 'Main Hall',
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/gatherings', [
                'tenant_id' => $tenant->id,
                'name' => 'Team meeting',
                'starts_at' => '2025-05-01 10:00:00',
                'ends_at' => '2025-05-01 11:00:00',
                'location' => 'Main Hall',
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Conflicts with existing gathering', $response->json('errors.starts_at.0'));
    }

    public function test_non_conflicting_gatherings_can_be_scheduled(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        Gathering::factory()->create([
            'tenant_id' => $tenant->id,
            'starts_at' => Carbon::parse('2025-05-01 09:00'),
            'ends_at' => Carbon::parse('2025-05-01 10:30'),
            'location' => 'Main Hall',
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/gatherings', [
                'tenant_id' => $tenant->id,
                'name' => 'Youth rehearsal',
                'starts_at' => '2025-05-01 11:00:00',
                'ends_at' => '2025-05-01 12:00:00',
                'location' => 'Main Hall',
            ]);

        $response->assertCreated();
    }
}
