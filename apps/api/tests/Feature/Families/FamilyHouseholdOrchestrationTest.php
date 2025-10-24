<?php

declare(strict_types=1);

namespace Tests\Feature\Families;

use App\Jobs\SendNotificationJob;
use App\Models\Family;
use App\Models\Member;
use App\Models\MemberContact;
use App\Models\Notification;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FamilyHouseholdOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_assigns_primary_contact_when_missing(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $guardian = Member::factory()->create(['tenant_id' => $tenant->id]);
        $child = Member::factory()->create(['tenant_id' => $tenant->id]);
        MemberContact::factory()->forMember($guardian)->state(['type' => 'email', 'value' => 'guardian@example.com'])->create();

        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'family_name' => 'Smith Household',
            'members' => [
                ['member_id' => $guardian->id, 'relationship' => 'guardian'],
                ['member_id' => $child->id, 'relationship' => 'child'],
            ],
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/families', $payload);

        $response->assertCreated();

        $family = Family::query()->with('members')->firstOrFail();
        $primaryMembers = $family->members->filter(fn ($member) => (bool) $member->pivot->is_primary_contact);

        $this->assertCount(1, $primaryMembers);
        $this->assertTrue($primaryMembers->first()->is($guardian));

        $this->assertDatabaseHas('family_members', [
            'family_id' => $family->id,
            'member_id' => $guardian->id,
            'is_primary_contact' => 1,
            'is_emergency_contact' => 1,
        ]);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'member_id' => $guardian->id,
        ]);

        Queue::assertPushed(SendNotificationJob::class);
    }

    public function test_enforces_single_primary_contact_on_update(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $memberA = Member::factory()->create(['tenant_id' => $tenant->id]);
        $memberB = Member::factory()->create(['tenant_id' => $tenant->id]);
        MemberContact::factory()->forMember($memberA)->state(['type' => 'email', 'value' => 'a@example.com'])->create();
        MemberContact::factory()->forMember($memberB)->state(['type' => 'email', 'value' => 'b@example.com'])->create();

        $this->actingAsTenantAdmin($tenant);

        $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/families', [
                'family_name' => 'Primary Household',
                'members' => [
                    ['member_id' => $memberA->id, 'relationship' => 'head', 'is_primary_contact' => true],
                    ['member_id' => $memberB->id, 'relationship' => 'spouse'],
                ],
            ])
            ->assertCreated();

        $family = Family::firstOrFail();

        $updatePayload = [
            'members' => [
                ['member_id' => $memberA->id, 'relationship' => 'head', 'is_primary_contact' => true],
                ['member_id' => $memberB->id, 'relationship' => 'spouse', 'is_primary_contact' => true],
            ],
        ];

        $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->putJson('/api/v1/families/' . $family->id, $updatePayload)
            ->assertOk();

        $family->refresh();
        $family->load('members');

        $primaries = $family->members->filter(fn ($member) => (bool) $member->pivot->is_primary_contact);
        $this->assertCount(1, $primaries);
        $this->assertTrue($primaries->first()->is($memberA));
    }

    public function test_assigns_head_relationship_when_missing(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        MemberContact::factory()->forMember($member)->state(['type' => 'email', 'value' => 'head@example.com'])->create();

        $this->actingAsTenantAdmin($tenant);

        $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/families', [
                'family_name' => 'Single Household',
                'members' => [
                    ['member_id' => $member->id],
                ],
            ])
            ->assertCreated();

        $family = Family::query()->with('members')->firstOrFail();
        $assignment = $family->members->first()->pivot;

        $this->assertDatabaseHas('family_members', [
            'family_id' => $family->id,
            'member_id' => $assignment->member_id,
            'is_primary_contact' => 1,
            'relationship' => 'head',
        ]);
    }

    public function test_primary_change_triggers_notification(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $memberA = Member::factory()->create(['tenant_id' => $tenant->id]);
        $memberB = Member::factory()->create(['tenant_id' => $tenant->id]);
        MemberContact::factory()->forMember($memberA)->state(['type' => 'email', 'value' => 'a@example.com'])->create();
        MemberContact::factory()->forMember($memberB)->state(['type' => 'email', 'value' => 'b@example.com'])->create();

        $this->actingAsTenantAdmin($tenant);

        $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/families', [
                'family_name' => 'Switch Household',
                'members' => [
                    ['member_id' => $memberA->id, 'relationship' => 'head', 'is_primary_contact' => true],
                    ['member_id' => $memberB->id, 'relationship' => 'spouse'],
                ],
            ])
            ->assertCreated();

        Notification::query()->forceDelete();
        Queue::fake();

        $family = Family::firstOrFail();

        $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->putJson('/api/v1/families/' . $family->id, [
                'members' => [
                    ['member_id' => $memberA->id, 'relationship' => 'head', 'is_primary_contact' => false],
                    ['member_id' => $memberB->id, 'relationship' => 'guardian'],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('family_members', [
            'family_id' => $family->id,
            'member_id' => $memberB->id,
            'is_primary_contact' => true,
        ]);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'member_id' => $memberB->id,
        ]);
    }

    public function test_family_communication_endpoint_queues_notifications(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $primary = Member::factory()->create(['tenant_id' => $tenant->id]);
        $emergency = Member::factory()->create(['tenant_id' => $tenant->id]);
        MemberContact::factory()->forMember($primary)->state(['type' => 'email', 'value' => 'primary@example.com'])->create();
        MemberContact::factory()->forMember($emergency)->state(['type' => 'email', 'value' => 'emergency@example.com'])->create();

        $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/families', [
                'family_name' => 'Comms Household',
                'members' => [
                    ['member_id' => $primary->id, 'relationship' => 'head', 'is_primary_contact' => true],
                    ['member_id' => $emergency->id, 'relationship' => 'spouse', 'is_emergency_contact' => true],
                ],
            ]);

        $response->assertCreated();
        $family = Family::firstOrFail();

        Notification::query()->forceDelete();

        $payload = [
            'channel' => 'email',
            'subject' => 'Household update',
            'body' => 'Reminder about this week.',
            'target' => 'all',
        ];

        $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/families/' . $family->id . '/communications', $payload)
            ->assertOk()
            ->assertJson(['data' => ['queued' => 2]]);

        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_family_communication_endpoint_returns_error_when_no_recipients(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        MemberContact::factory()->forMember($member)->state(['type' => 'email', 'value' => 'member@example.com'])->create();

        $this->actingAsTenantAdmin($tenant);

        $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/families', [
                'family_name' => 'Solo Household',
            ])
            ->assertCreated();

        $family = Family::firstOrFail();

        $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/families/' . $family->id . '/communications', [
                'channel' => 'email',
                'subject' => 'Reminder',
                'body' => 'Update',
                'target' => 'primary',
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'No recipients available for the selected household target.']);
    }
}
