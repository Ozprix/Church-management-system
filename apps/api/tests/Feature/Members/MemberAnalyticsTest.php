<?php

declare(strict_types=1);

namespace Tests\Feature\Members;

use App\Models\Member;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_analytics_returns_metrics_with_filters(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        Member::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'membership_status' => 'active',
            'membership_stage' => 'newcomer',
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/members/analytics');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'totals' => [
                    'members',
                    'members_without_family',
                    'stale_profiles',
                ],
                'by_status',
                'by_stage',
                'new_members_trend',
                'recent_members',
                'filters' => [
                    'statuses',
                    'stages',
                    'joined_range' => ['earliest', 'latest'],
                ],
            ]);
    }
}
