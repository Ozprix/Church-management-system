<?php

declare(strict_types=1);

namespace Tests\Feature\Volunteer;

use App\Models\Member;
use App\Models\Tenant;
use App\Models\VolunteerAssignment;
use App\Models\VolunteerRole;
use App\Models\VolunteerSignup;
use App\Services\VolunteerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VolunteerAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_volunteer_analytics_summary(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser($tenant, roles: ['admin']);

        $role = VolunteerRole::factory()->create(['tenant_id' => $tenant->id]);

        VolunteerAssignment::factory()->create([
            'tenant_id' => $tenant->id,
            'volunteer_role_id' => $role->id,
            'member_id' => Member::factory()->create(['tenant_id' => $tenant->id])->id,
            'volunteer_team_id' => null,
            'gathering_id' => null,
            'starts_at' => Carbon::now()->addDay(),
            'status' => 'confirmed',
        ]);

        VolunteerAssignment::factory()->create([
            'tenant_id' => $tenant->id,
            'volunteer_role_id' => $role->id,
            'volunteer_team_id' => null,
            'gathering_id' => null,
            'member_id' => Member::factory()->create(['tenant_id' => $tenant->id])->id,
            'starts_at' => Carbon::now()->addDays(2),
            'status' => 'scheduled',
        ]);

        VolunteerSignup::factory()->create([
            'tenant_id' => $tenant->id,
            'volunteer_role_id' => $role->id,
            'status' => 'pending',
        ]);

        app(VolunteerService::class)->refreshRoleAnalytics($role->fresh());

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->getJson('/api/v1/volunteer/analytics/summary');

        $response->assertOk()
            ->assertJsonPath('data.top_roles.0.id', $role->id)
            ->assertJsonPath('data.signups_by_status.pending', 1)
            ->assertJsonPath('data.open_assignments', 1);
    }
}
