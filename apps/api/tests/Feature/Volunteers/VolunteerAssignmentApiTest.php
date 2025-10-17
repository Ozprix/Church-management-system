<?php

declare(strict_types=1);

namespace Tests\Feature\Volunteers;

use App\Models\Gathering;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\VolunteerAssignment;
use App\Models\VolunteerRole;
use App\Models\VolunteerTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolunteerAssignmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_assignment(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $role = VolunteerRole::factory()->create(['tenant_id' => $tenant->id]);
        $team = VolunteerTeam::factory()->create(['tenant_id' => $tenant->id]);
        $gathering = Gathering::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'member_id' => $member->id,
            'volunteer_role_id' => $role->id,
            'volunteer_team_id' => $team->id,
            'gathering_id' => $gathering->id,
            'starts_at' => now()->addWeek()->toIso8601String(),
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/volunteer-assignments', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('volunteer_assignments', [
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'volunteer_role_id' => $role->id,
        ]);
    }

    public function test_it_swaps_assignments(): void
    {
        $tenant = Tenant::factory()->create();
        $members = Member::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        $role = VolunteerRole::factory()->create(['tenant_id' => $tenant->id]);

        $firstAssignment = VolunteerAssignment::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $members[0]->id,
            'volunteer_role_id' => $role->id,
        ]);

        $secondAssignment = VolunteerAssignment::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $members[1]->id,
            'volunteer_role_id' => $role->id,
        ]);

        $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson("/api/v1/volunteer-assignments/{$firstAssignment->id}/swap", [
                'target_assignment_id' => $secondAssignment->id,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('volunteer_assignments', [
            'id' => $firstAssignment->id,
            'member_id' => $members[1]->id,
            'status' => 'swapped',
        ]);

        $this->assertDatabaseHas('volunteer_assignments', [
            'id' => $secondAssignment->id,
            'member_id' => $members[0]->id,
            'status' => 'swapped',
        ]);
    }
}
