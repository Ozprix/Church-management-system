<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Fund;
use App\Models\Member;
use App\Models\MemberContact;
use App\Models\Notification;
use App\Models\Pledge;
use App\Models\Tenant;
use App\Support\PledgeReminderCadence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PledgeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_pledge(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'member_id' => $member->id,
            'fund_id' => $fund->id,
            'amount' => 500,
            'currency' => 'USD',
            'frequency' => 'monthly',
            'start_date' => Carbon::parse('2024-02-01')->toDateString(),
            'status' => 'active',
            'notes' => 'Building campaign pledge.',
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/pledges', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.amount', '500.00')
            ->assertJsonPath('data.member.id', $member->id)
            ->assertJsonPath('data.fund.id', $fund->id);

        $this->assertDatabaseHas('pledges', [
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'fund_id' => $fund->id,
            'amount' => 500.00,
            'status' => 'active',
        ]);
    }

    public function test_it_updates_pledge(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);
        $newFund = Fund::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        $pledge = Pledge::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'fund_id' => $fund->id,
            'amount' => 200.00,
            'status' => 'active',
        ]);

        $payload = [
            'fund_id' => $newFund->id,
            'amount' => 300,
            'status' => 'fulfilled',
            'fulfilled_amount' => 300,
            'notes' => 'Completed ahead of schedule.',
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->putJson("/api/v1/pledges/{$pledge->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.amount', '300.00')
            ->assertJsonPath('data.status', 'fulfilled')
            ->assertJsonPath('data.fund.id', $newFund->id);

        $this->assertDatabaseHas('pledges', [
            'id' => $pledge->id,
            'fund_id' => $newFund->id,
            'amount' => 300.00,
            'fulfilled_amount' => 300.00,
            'status' => 'fulfilled',
        ]);
    }

    public function test_pledge_index_filters_by_status(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        Pledge::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'fund_id' => $fund->id,
            'status' => 'active',
        ]);

        Pledge::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'fund_id' => $fund->id,
            'status' => 'fulfilled',
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/pledges?status=fulfilled');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('fulfilled', $response->json('data.0.status'));
    }

    public function test_it_enables_pledge_reminders(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-02-01 09:00:00', 'UTC'));

        try {
            $tenant = Tenant::factory()->create();
            $member = Member::factory()->create(['tenant_id' => $tenant->id]);
            $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);

            $this->actingAsTenantAdmin($tenant);

            $pledge = Pledge::factory()->create([
                'tenant_id' => $tenant->id,
                'member_id' => $member->id,
                'fund_id' => $fund->id,
                'start_date' => Carbon::parse('2024-01-01'),
                'status' => 'active',
                'reminder_enabled' => false,
            ]);

            $payload = [
                'start_date' => Carbon::parse('2024-02-01')->toDateString(),
                'reminder_enabled' => true,
                'reminder_cadence' => PledgeReminderCadence::WEEKLY,
            ];

            $response = $this
                ->withHeader('X-Tenant-ID', $tenant->uuid)
                ->putJson("/api/v1/pledges/{$pledge->id}", $payload);

            $response->assertOk()
                ->assertJsonPath('data.reminder_enabled', true)
                ->assertJsonPath('data.reminder_cadence', PledgeReminderCadence::WEEKLY);

            $this->assertNotNull($response->json('data.next_reminder_at'));

            $pledge->refresh();

            $this->assertTrue($pledge->reminder_enabled);
            $this->assertSame(PledgeReminderCadence::WEEKLY, $pledge->reminder_cadence);
            $this->assertNotNull($pledge->next_reminder_at);
            $this->assertTrue($pledge->next_reminder_at->equalTo(Carbon::parse('2024-02-08 00:00:00', 'UTC')));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_disabling_reminders_clears_schedule(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        $pledge = Pledge::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'fund_id' => $fund->id,
            'status' => 'active',
            'reminder_enabled' => true,
            'reminder_cadence' => PledgeReminderCadence::MONTHLY,
            'next_reminder_at' => Carbon::now()->subDay(),
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->putJson("/api/v1/pledges/{$pledge->id}", [
                'reminder_enabled' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.reminder_enabled', false)
            ->assertJsonPath('data.reminder_cadence', null)
            ->assertJsonPath('data.next_reminder_at', null);

        $pledge->refresh();

        $this->assertFalse($pledge->reminder_enabled);
        $this->assertNull($pledge->reminder_cadence);
        $this->assertNull($pledge->next_reminder_at);
    }

    public function test_it_returns_reminder_history_for_pledge(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        $pledge = Pledge::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'fund_id' => $fund->id,
            'status' => 'active',
        ]);

        MemberContact::factory()->forMember($member)->state([
            'type' => 'email',
            'value' => 'member@example.test',
            'is_primary' => true,
        ])->create();

        Notification::factory()
            ->forMember($member)
            ->sent()
            ->state([
                'tenant_id' => $tenant->id,
                'payload' => [
                    'pledge_id' => $pledge->id,
                    'reminder_sent_at' => now()->toIso8601String(),
                ],
            ])
            ->create();

        Notification::factory()
            ->state([
                'tenant_id' => $tenant->id,
                'payload' => ['pledge_id' => 9999],
            ])
            ->create();

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson("/api/v1/pledges/{$pledge->id}/reminders");

        $response->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('sent', $response->json('data.0.status'));
        $this->assertSame($pledge->id, $response->json('data.0.payload.pledge_id'));
    }

    public function test_reminder_history_filters_by_channel_and_search(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        $pledge = Pledge::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'fund_id' => $fund->id,
            'status' => 'active',
        ]);

        MemberContact::factory()->forMember($member)->state([
            'type' => 'email',
            'value' => 'member@example.test',
            'is_primary' => true,
        ])->create();

        Notification::factory()
            ->forMember($member)
            ->sent()
            ->state([
                'tenant_id' => $tenant->id,
                'channel' => 'email',
                'subject' => 'Monthly pledge reminder',
                'payload' => ['pledge_id' => $pledge->id],
            ])
            ->create();

        Notification::factory()
            ->state([
                'tenant_id' => $tenant->id,
                'channel' => 'sms',
                'subject' => 'Another pledge reminder',
                'payload' => ['pledge_id' => $pledge->id],
            ])
            ->create();

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson("/api/v1/pledges/{$pledge->id}/reminders?channel=email&q=Monthly");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('email', $response->json('data.0.channel'));
        $this->assertSame('Monthly pledge reminder', $response->json('data.0.subject'));
    }
}
