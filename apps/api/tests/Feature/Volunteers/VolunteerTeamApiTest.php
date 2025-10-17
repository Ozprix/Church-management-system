<?php

declare(strict_types=1);

namespace Tests\Feature\Volunteers;

use App\Models\Tenant;
use App\Models\VolunteerRole;
use App\Models\VolunteerTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolunteerTeamApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_teams_with_roles(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $hospitality = VolunteerTeam::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Guest Experience',
            'slug' => 'guest-experience',
        ]);

        $production = VolunteerTeam::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Production Crew',
            'slug' => 'production-crew',
        ]);

        $role = VolunteerRole::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Sunday Greeter',
        ]);

        $hospitality->roles()->attach($role->id);

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->getJson('/api/v1/volunteer-teams?per_page=10');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.name', 'Guest Experience');
        $response->assertJsonPath('data.0.roles.0.id', $role->id);
        $response->assertJsonPath('data.1.name', 'Production Crew');
        $this->assertNotEmpty($response->json('meta'));
    }

    public function test_it_creates_team_and_syncs_roles(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $roleOne = VolunteerRole::factory()->create(['tenant_id' => $tenant->id]);
        $roleTwo = VolunteerRole::factory()->create(['tenant_id' => $tenant->id]);

        $payload = [
            'name' => 'Hospitality Team',
            'slug' => 'hospitality-team',
            'description' => 'Welcomes attendees and oversees guest services.',
            'metadata' => ['contact_email' => 'hospitality@example.test'],
            'role_ids' => [$roleOne->id, $roleTwo->id],
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/volunteer-teams', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Hospitality Team')
            ->assertJsonPath('data.metadata.contact_email', 'hospitality@example.test');

        $this->assertDatabaseHas('volunteer_teams', [
            'tenant_id' => $tenant->id,
            'slug' => 'hospitality-team',
            'description' => 'Welcomes attendees and oversees guest services.',
        ]);

        $teamId = $response->json('data.id');

        $this->assertDatabaseHas('volunteer_role_team', [
            'volunteer_team_id' => $teamId,
            'volunteer_role_id' => $roleOne->id,
        ]);

        $this->assertDatabaseHas('volunteer_role_team', [
            'volunteer_team_id' => $teamId,
            'volunteer_role_id' => $roleTwo->id,
        ]);
    }

    public function test_it_shows_team_with_roles(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $team = VolunteerTeam::factory()->create(['tenant_id' => $tenant->id]);
        $role = VolunteerRole::factory()->create(['tenant_id' => $tenant->id]);
        $team->roles()->attach($role->id);

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->getJson("/api/v1/volunteer-teams/{$team->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $team->id)
            ->assertJsonPath('data.roles.0.id', $role->id);
    }

    public function test_it_updates_team_and_syncs_roles(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $team = VolunteerTeam::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Care Team',
        ]);

        $currentRole = VolunteerRole::factory()->create(['tenant_id' => $tenant->id]);
        $replacementRole = VolunteerRole::factory()->create(['tenant_id' => $tenant->id]);
        $team->roles()->attach($currentRole->id);

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->patchJson("/api/v1/volunteer-teams/{$team->id}", [
                'name' => 'Care & Followup Team',
                'metadata' => ['contact' => 'followup@example.test'],
                'role_ids' => [$replacementRole->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Care & Followup Team')
            ->assertJsonPath('data.metadata.contact', 'followup@example.test')
            ->assertJsonPath('data.roles.0.id', $replacementRole->id);

        $this->assertDatabaseHas('volunteer_teams', [
            'id' => $team->id,
            'name' => 'Care & Followup Team',
        ]);

        $this->assertDatabaseHas('volunteer_role_team', [
            'volunteer_team_id' => $team->id,
            'volunteer_role_id' => $replacementRole->id,
        ]);

        $this->assertDatabaseMissing('volunteer_role_team', [
            'volunteer_team_id' => $team->id,
            'volunteer_role_id' => $currentRole->id,
        ]);
    }

    public function test_it_deletes_team(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $team = VolunteerTeam::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->deleteJson("/api/v1/volunteer-teams/{$team->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('volunteer_teams', [
            'id' => $team->id,
            'tenant_id' => $tenant->id,
        ]);
    }
}
