<?php

declare(strict_types=1);

namespace Tests\Feature\Visitors;

use App\Models\Tenant;
use App\Models\VisitorFollowup;
use App\Models\VisitorFollowupLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorFollowupLogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_followup_logs(): void
    {
        $tenant = Tenant::factory()->create();
        $followup = VisitorFollowup::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        VisitorFollowupLog::factory()->count(3)->create([
            'followup_id' => $followup->id,
        ]);

        $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/visitor-followups/' . $followup->id . '/logs');

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
