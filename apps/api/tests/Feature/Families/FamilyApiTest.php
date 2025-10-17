<?php

declare(strict_types=1);

namespace Tests\Feature\Families;

use App\Models\Family;
use App\Models\Member;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_family_with_members(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'family_name' => 'Smith Family',
            'members' => [
                [
                    'member_id' => $member->id,
                    'relationship' => 'head',
                    'is_primary_contact' => true,
                ],
            ],
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/families', $payload);

        $response->assertCreated();
        $familyId = Family::where('tenant_id', $tenant->id)->value('id');
        $this->assertDatabaseHas('family_members', [
            'family_id' => $familyId,
            'member_id' => $member->id,
            'relationship' => 'head',
        ]);
    }

    public function test_family_index_only_returns_current_tenant(): void
    {
        $tenant = Tenant::factory()->has(Family::factory()->count(2))->create();
        Tenant::factory()->has(Family::factory()->count(3))->create();

        $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/families');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }
}
