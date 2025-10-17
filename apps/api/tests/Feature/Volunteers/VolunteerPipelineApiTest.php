<?php

declare(strict_types=1);

namespace Tests\Feature\Volunteers;

use App\Models\Member;
use App\Models\Tenant;
use App\Models\VolunteerAssignment;
use App\Models\VolunteerRole;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolunteerPipelineApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_updates_signup(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser($tenant, roles: ['admin']);
        $role = VolunteerRole::factory()->create(['tenant_id' => $tenant->id]);
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);

        $payload = [
            'tenant_id' => $tenant->id,
            'volunteer_role_id' => $role->id,
            'member_id' => $member->id,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/volunteer-signups', $payload);

        $response->assertCreated()->assertJsonPath('data.name', 'Jane Doe');

        $signupId = $response->json('data.id');

        $assignmentStarts = Carbon::now()->addDays(2)->toIso8601String();

        $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->patchJson("/api/v1/volunteer-signups/{$signupId}", [
                'status' => 'confirmed',
                'assignment' => [
                    'starts_at' => $assignmentStarts,
                    'member_id' => $member->id,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned');

        $this->assertDatabaseHas('volunteer_assignments', [
            'tenant_id' => $tenant->id,
            'volunteer_role_id' => $role->id,
            'member_id' => $member->id,
            'starts_at' => Carbon::parse($assignmentStarts)->toDateTimeString(),
        ]);

        $role->refresh();
        $this->assertGreaterThanOrEqual(1, $role->active_assignment_count);
        $this->assertSame(0, $role->pending_signup_count);
    }

    public function test_it_records_volunteer_hours(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser($tenant, roles: ['admin']);
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $assignment = VolunteerAssignment::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'volunteer_team_id' => null,
            'gathering_id' => null,
        ]);

        $payload = [
            'tenant_id' => $tenant->id,
            'volunteer_assignment_id' => $assignment->id,
            'member_id' => $member->id,
            'served_on' => now()->toDateString(),
            'hours' => 3,
            'source' => 'manual',
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/volunteer-hours', $payload);

        $response->assertCreated()->assertJsonPath('data.hours', '3.00');

        $this->assertDatabaseHas('volunteer_hours', [
            'tenant_id' => $tenant->id,
            'volunteer_assignment_id' => $assignment->id,
            'hours' => 3.00,
        ]);
    }
}
