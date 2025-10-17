<?php

declare(strict_types=1);

namespace Tests\Feature\Volunteers;

use App\Models\Tenant;
use App\Models\VolunteerRole;
use App\Models\VolunteerTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolunteerRoleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_role(): void
    {
        $tenant = Tenant::factory()->create();
        $team = VolunteerTeam::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'name' => 'Greeter',
            'slug' => 'greeter',
            'description' => 'Welcome guests at the entrance.',
            'skills_required' => ['hospitality'],
            'team_ids' => [$team->id],
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/volunteer-roles', $payload);

        $response->assertCreated()->assertJsonPath('data.name', 'Greeter');
        $this->assertDatabaseHas('volunteer_roles', [
            'tenant_id' => $tenant->id,
            'slug' => 'greeter',
        ]);
    }

    public function test_it_updates_role_and_syncs_teams(): void
    {
        $role = VolunteerRole::factory()->create();
        $tenant = $role->tenant;
        $team = VolunteerTeam::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->patchJson("/api/v1/volunteer-roles/{$role->id}", [
                'name' => 'Updated Role',
                'team_ids' => [$team->id],
            ]);

        $response->assertOk()->assertJsonPath('data.name', 'Updated Role');
        $this->assertDatabaseHas('volunteer_role_team', [
            'volunteer_role_id' => $role->id,
            'volunteer_team_id' => $team->id,
        ]);
    }
}
