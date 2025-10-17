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

        $familyWithoutPrimary = Family::factory()->create([
            'tenant_id' => $tenant->id,
            'address' => [
                'line1' => '456 Side St',
                'city' => 'Riverdale',
                'state' => 'CA',
                'postal_code' => '90001',
                'country' => 'US',
            ],
        ]);

        $memberWithoutPrimary = Member::factory()->create(['tenant_id' => $tenant->id]);

        FamilyMember::create([
            'tenant_id' => $tenant->id,
            'family_id' => $familyWithoutPrimary->id,
            'member_id' => $memberWithoutPrimary->id,
            'relationship' => 'child',
            'is_primary_contact' => false,
            'is_emergency_contact' => false,
        ]);

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
            'families_missing_primary',
        ]);

        $response->assertJsonPath('families_missing_primary.0.family_name', $familyWithoutPrimary->family_name);
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
