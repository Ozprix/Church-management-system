<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_payment_method_and_sets_default(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        $firstPayload = [
            'member_id' => $member->id,
            'type' => 'card',
            'brand' => 'Visa',
            'last_four' => '4242',
            'provider' => 'stripe',
            'is_default' => true,
        ];

        $firstResponse = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/payment-methods', $firstPayload);

        $firstResponse->assertCreated();

        $firstId = $firstResponse->json('data.id');

        $secondPayload = [
            'member_id' => $member->id,
            'type' => 'mobile_money',
            'brand' => 'MTN',
            'last_four' => '1111',
            'provider' => 'hubtel',
            'is_default' => true,
        ];

        $secondResponse = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/payment-methods', $secondPayload);

        $secondResponse->assertCreated();

        $this->assertDatabaseHas('payment_methods', [
            'id' => $firstId,
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('payment_methods', [
            'id' => $secondResponse->json('data.id'),
            'is_default' => true,
        ]);
    }

    public function test_it_updates_payment_method_and_changes_default(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $alternate = Member::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        $method = PaymentMethod::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'type' => 'card',
            'brand' => 'Visa',
            'last_four' => '4444',
            'provider' => 'stripe',
            'is_default' => true,
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->putJson("/api/v1/payment-methods/{$method->id}", [
                'member_id' => $alternate->id,
                'type' => 'bank',
                'brand' => 'GCB',
                'is_default' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.member.id', $alternate->id)
            ->assertJsonPath('data.type', 'bank')
            ->assertJsonPath('data.is_default', false);

        $this->assertDatabaseHas('payment_methods', [
            'id' => $method->id,
            'member_id' => $alternate->id,
            'type' => 'bank',
            'brand' => 'GCB',
            'is_default' => false,
        ]);
    }

    public function test_it_filters_payment_methods_by_member(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $otherMember = Member::factory()->create(['tenant_id' => $tenant->id]);

        PaymentMethod::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
        ]);

        PaymentMethod::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $otherMember->id,
        ]);

        $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson("/api/v1/payment-methods?member_id={$member->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }
}
