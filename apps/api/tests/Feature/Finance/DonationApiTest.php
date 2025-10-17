<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Donation;
use App\Models\Fund;
use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DonationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_donation_and_items(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $paymentMethod = PaymentMethod::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
        ]);
        $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);
        $payload = [
            'member_id' => $member->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 250,
            'currency' => 'USD',
            'status' => 'succeeded',
            'received_at' => Carbon::parse('2024-02-04 09:30:00')->toIso8601String(),
            'notes' => 'Monthly tithe.',
            'items' => [
                [
                    'fund_id' => $fund->id,
                    'amount' => 250,
                ],
            ],
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/donations', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.amount', '250.00')
            ->assertJsonPath('data.items.0.fund.id', $fund->id);

        $donationId = Donation::query()->where('tenant_id', $tenant->id)->value('id');

        $this->assertDatabaseHas('donations', [
            'id' => $donationId,
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'amount' => 250.00,
            'status' => 'succeeded',
        ]);

        $this->assertDatabaseHas('donation_items', [
            'donation_id' => $donationId,
            'fund_id' => $fund->id,
            'amount' => 250.00,
        ]);

        $ledgerEntries = Donation::query()->find($donationId)?->ledgerEntries()->get();
        $this->assertSame(2, $ledgerEntries->count());

        $incomeEntry = $ledgerEntries->firstWhere('entry_type', 'credit');
        $assetEntry = $ledgerEntries->firstWhere('entry_type', 'debit');

        $this->assertNotNull($incomeEntry);
        $this->assertSame('Donations Income', $incomeEntry->account);
        $this->assertSame('donation', $incomeEntry->metadata['kind'] ?? null);
        $this->assertEquals(250.00, (float) $incomeEntry->amount);

        $this->assertNotNull($assetEntry);
        $this->assertSame('Cash - Undeposited Funds', $assetEntry->account);
        $this->assertSame('donation', $assetEntry->metadata['kind'] ?? null);
        $this->assertEquals(250.00, (float) $assetEntry->amount);
    }

    public function test_donation_update_replaces_items(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);
        $anotherFund = Fund::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);
        $createPayload = [
            'member_id' => $member->id,
            'amount' => 100,
            'currency' => 'USD',
            'items' => [
                ['fund_id' => $fund->id, 'amount' => 100],
            ],
        ];

        $donationResponse = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/donations', $createPayload);
        $donationResponse->assertCreated();

        $donationId = $donationResponse->json('data.id');

        $updatePayload = [
            'amount' => 150,
            'items' => [
                ['fund_id' => $anotherFund->id, 'amount' => 150],
            ],
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->putJson("/api/v1/donations/{$donationId}", $updatePayload);

        $response->assertOk()
            ->assertJsonPath('data.amount', '150.00')
            ->assertJsonPath('data.items.0.fund.id', $anotherFund->id);

        $this->assertDatabaseMissing('donation_items', [
            'donation_id' => $donationId,
            'fund_id' => $fund->id,
        ]);

        $this->assertDatabaseHas('donation_items', [
            'donation_id' => $donationId,
            'fund_id' => $anotherFund->id,
            'amount' => 150.00,
        ]);

        $ledgerEntries = Donation::query()->find($donationId)?->ledgerEntries()->orderBy('id')->get();
        $this->assertSame(2, $ledgerEntries->count());
        $this->assertEquals(150.00, (float) $ledgerEntries->first()->amount);
        $this->assertEquals(150.00, (float) $ledgerEntries->last()->amount);
    }

    public function test_it_rejects_donation_items_for_other_tenants(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $foreignFund = Fund::factory()->create();

        $this->actingAsTenantAdmin($tenant);
        $payload = [
            'member_id' => $member->id,
            'amount' => 75,
            'currency' => 'USD',
            'items' => [
                ['fund_id' => $foreignFund->id, 'amount' => 75],
            ],
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/donations', $payload);

        $response->assertNotFound();
        $this->assertDatabaseCount('donations', 0);
        $this->assertDatabaseCount('donation_items', 0);
        $this->assertDatabaseCount('financial_ledger_entries', 0);
    }

    public function test_refunding_donation_creates_reversing_ledger_entries(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);
        $createPayload = [
            'member_id' => $member->id,
            'amount' => 120,
            'currency' => 'USD',
            'status' => 'succeeded',
            'items' => [
                ['fund_id' => $fund->id, 'amount' => 120],
            ],
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/donations', $createPayload);
        $response->assertCreated();

        $donationId = $response->json('data.id');

        $refundResponse = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->putJson("/api/v1/donations/{$donationId}", ['status' => 'refunded']);

        $refundResponse->assertOk()->assertJsonPath('data.status', 'refunded');

        $ledgerEntries = Donation::query()->find($donationId)?->ledgerEntries()->orderBy('id')->get();
        $this->assertSame(4, $ledgerEntries->count());

        $kinds = $ledgerEntries->pluck('metadata.kind')->all();
        $this->assertSame(['donation', 'donation', 'refund', 'refund'], $kinds);

        $this->assertSame(['credit', 'debit', 'debit', 'credit'], $ledgerEntries->pluck('entry_type')->all());
    }
}
