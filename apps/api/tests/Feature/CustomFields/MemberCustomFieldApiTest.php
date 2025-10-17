<?php

declare(strict_types=1);

namespace Tests\Feature\CustomFields;

use App\Models\Member;
use App\Models\MemberCustomField;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberCustomFieldApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_custom_field(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'name' => 'Baptism Date',
            'data_type' => 'date',
            'is_required' => true,
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/member-custom-fields', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('member_custom_fields', [
            'tenant_id' => $tenant->id,
            'name' => 'Baptism Date',
            'data_type' => 'date',
        ]);
    }

    public function test_it_prevents_deleting_field_with_values(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $field = MemberCustomField::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAsTenantAdmin($tenant);

        $field->values()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'value_string' => 'Example',
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->deleteJson("/api/v1/member-custom-fields/{$field->id}");

        $response->assertStatus(422);
    }
}
