<?php

declare(strict_types=1);

namespace Tests\Feature\Families;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Member;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_analytics_returns_metrics(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $family = Family::factory()->create(['tenant_id' => $tenant->id]);
        $members = Member::factory()->count(4)->create(['tenant_id' => $tenant->id]);

        foreach ($members as $index => $member) {
            FamilyMember::create([
                'tenant_id' => $tenant->id,
                'family_id' => $family->id,
                'member_id' => $member->id,
                'relationship' => $index === 0 ? 'head' : ($index % 2 === 0 ? 'child' : 'spouse'),
                'is_primary_contact' => $index === 0,
                'is_emergency_contact' => $index === 1,
            ]);
        }

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/families/analytics');

        $response->assertOk();
        $response->assertJsonStructure([
            'totals' => [
                'families',
                'average_household_size',
                'families_with_children',
                'families_without_primary_contact',
            ],
            'size_distribution',
            'by_relationship',
            'recent_families',
        ]);
    }

    public function test_family_analytics_export_streams_csv(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $family = Family::factory()->create(['tenant_id' => $tenant->id]);
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);

        FamilyMember::create([
            'tenant_id' => $tenant->id,
            'family_id' => $family->id,
            'member_id' => $member->id,
            'relationship' => 'head',
            'is_primary_contact' => true,
            'is_emergency_contact' => true,
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->get('/api/v1/families/analytics/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
