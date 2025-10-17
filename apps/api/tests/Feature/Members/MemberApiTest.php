<?php

declare(strict_types=1);

namespace Tests\Feature\Members;

use App\Models\AuditLog;
use App\Jobs\ProcessMemberImport;
use App\Models\Family;
use App\Models\Member;
use App\Models\MemberContact;
use App\Models\MemberCustomField;
use App\Models\MemberImport;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemberApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_member_with_contacts_and_custom_values(): void
    {
        $tenant = Tenant::factory()->create();
        $family = Family::factory()->create(['tenant_id' => $tenant->id]);
        $field = MemberCustomField::factory()->create(['tenant_id' => $tenant->id, 'data_type' => 'text']);

        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'membership_status' => 'active',
            'contacts' => [
                [
                    'type' => 'email',
                    'value' => 'ada@example.com',
                    'is_primary' => true,
                ],
                [
                    'type' => 'mobile',
                    'value' => '+15550001111',
                    'is_primary' => false,
                ],
            ],
            'families' => [
                [
                    'family_id' => $family->id,
                    'relationship' => 'head',
                    'is_primary_contact' => true,
                ],
            ],
            'custom_values' => [
                [
                    'field_id' => $field->id,
                    'value' => 'Piano',
                ],
            ],
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/members', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('members', [
            'tenant_id' => $tenant->id,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ]);

        $memberId = Member::where('tenant_id', $tenant->id)->value('id');
        $this->assertDatabaseHas('member_contacts', [
            'member_id' => $memberId,
            'type' => 'email',
            'value' => 'ada@example.com',
        ]);
        $this->assertDatabaseHas('family_members', [
            'family_id' => $family->id,
            'member_id' => $memberId,
            'relationship' => 'head',
        ]);
        $this->assertDatabaseHas('member_custom_values', [
            'member_id' => $memberId,
            'field_id' => $field->id,
            'value_string' => 'Piano',
        ]);
    }

    public function test_member_crud_happy_path(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $createPayload = [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'membership_status' => 'active',
            'contacts' => [
                [
                    'type' => 'email',
                    'value' => 'grace.hopper@example.test',
                    'is_primary' => true,
                ],
            ],
        ];

        $createResponse = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/members', $createPayload);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.first_name', 'Grace')
            ->assertJsonPath('data.membership_status', 'active');

        $memberUuid = $createResponse->json('data.uuid');
        $this->assertNotNull($memberUuid);

        $showResponse = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson("/api/v1/members/{$memberUuid}");

        $showResponse
            ->assertOk()
            ->assertJsonPath('data.uuid', $memberUuid)
            ->assertJsonPath('data.contacts.0.value', 'grace.hopper@example.test');

        $updatePayload = [
            'preferred_name' => 'Commodore Grace',
            'membership_status' => 'inactive',
        ];

        $updateResponse = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->putJson("/api/v1/members/{$memberUuid}", $updatePayload);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.preferred_name', 'Commodore Grace')
            ->assertJsonPath('data.membership_status', 'inactive');

        $deleteResponse = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->deleteJson("/api/v1/members/{$memberUuid}");

        $deleteResponse->assertNoContent();

        $deletedMember = Member::withTrashed()->firstWhere('uuid', $memberUuid);
        $this->assertNotNull($deletedMember);

        $this->assertSoftDeleted('members', [
            'id' => $deletedMember->id,
        ]);

        $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson("/api/v1/members/{$memberUuid}")
            ->assertNotFound();
    }

    public function test_member_creation_requires_last_name(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'first_name' => 'NoLastName',
            'membership_status' => 'active',
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/members', $payload);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['last_name']);

        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_member_bulk_import_creates_multiple_members(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'members' => [
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'membership_status' => 'active',
                    'contacts' => [
                        ['type' => 'email', 'value' => 'john.doe@example.test', 'is_primary' => true],
                    ],
                ],
                [
                    'first_name' => 'Jane',
                    'last_name' => 'Smith',
                    'membership_status' => 'prospect',
                ],
            ],
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/members/bulk-import', $payload);

        $response->assertCreated();

        $data = $response->json('data');

        $this->assertCount(2, $data);
        $this->assertNotNull($data[0]['uuid'] ?? null);
        $this->assertNotNull($data[1]['uuid'] ?? null);

        $this->assertDatabaseCount('members', 2);
        $this->assertDatabaseHas('members', ['first_name' => 'John', 'last_name' => 'Doe']);
        $this->assertDatabaseHas('members', ['first_name' => 'Jane', 'last_name' => 'Smith']);

        $this->assertSame(
            2,
            AuditLog::query()->where('action', 'member.created')->count()
        );
    }

    public function test_member_import_upload_dispatches_job(): void
    {
        Queue::fake();
        Storage::fake('local');

        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $csv = "first_name,last_name,email\nAda,Lovelace,ada@example.com\n";
        $file = UploadedFile::fake()->createWithContent('members.csv', $csv);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/member-imports', ['file' => $file]);

        $response->assertStatus(202);

        $importId = $response->json('data.id');
        $import = MemberImport::query()->find($importId);

        $this->assertNotNull($import);

        Queue::assertPushed(ProcessMemberImport::class, function ($job) use ($import) {
            return $job->memberImport->is($import);
        });
    }

    public function test_member_import_show_returns_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $import = MemberImport::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'original_filename' => 'members.csv',
            'stored_path' => 'member-imports/' . $tenant->id . '/members.csv',
            'status' => MemberImport::STATUS_PENDING,
            'total_rows' => 10,
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson("/api/v1/member-imports/{$import->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $import->id)
            ->assertJsonPath('data.status', MemberImport::STATUS_PENDING);
    }

    public function test_member_import_index_lists_imports(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        MemberImport::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/member-imports');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_process_member_import_job_processes_file(): void
    {
        Storage::fake('local');

        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $csv = "first_name,last_name,email\nGrace,Hopper,grace@example.com\n";
        $path = "member-imports/{$tenant->id}/test.csv";
        Storage::put($path, $csv);

        $import = MemberImport::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'original_filename' => 'test.csv',
            'stored_path' => $path,
            'status' => MemberImport::STATUS_PENDING,
        ]);

        $job = new ProcessMemberImport($import);
        $job->handle(app(\App\Services\MemberService::class), app(\App\Support\Tenancy\TenantManager::class));

        $import->refresh();

        $this->assertSame(MemberImport::STATUS_COMPLETED, $import->status);
        $this->assertEquals(1, $import->processed_rows);
        $this->assertDatabaseHas('members', [
            'tenant_id' => $tenant->id,
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
        ]);
    }

    public function test_member_audit_timeline_endpoint_returns_logs(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantAdmin($tenant);

        $member = Member::factory()->create([
            'tenant_id' => $tenant->id,
            'membership_status' => 'active',
        ]);

        AuditLog::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'action' => 'member.created',
            'auditable_type' => Member::class,
            'auditable_id' => $member->id,
            'payload' => ['attributes' => ['first_name' => $member->first_name]],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'occurred_at' => Carbon::now(),
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson("/api/v1/members/{$member->uuid}/audits");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'member.created');
    }

    public function test_member_bulk_delete_soft_deletes_members_and_logs(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $members = Member::factory()->count(3)->create(['tenant_id' => $tenant->id]);
        $targetUuids = $members->take(2)->pluck('uuid')->all();

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/members/bulk-delete', [
                'member_ids' => $targetUuids,
            ]);

        $response->assertOk()->assertJson(['deleted' => 2]);

        foreach ($targetUuids as $uuid) {
            $member = Member::withTrashed()->where('uuid', $uuid)->first();
            $this->assertNotNull($member);
            $this->assertNotNull($member->deleted_at);
        }

        $this->assertSame(
            2,
            AuditLog::query()->where('action', 'member.deleted')->count()
        );
    }

    public function test_member_bulk_import_rejects_over_limit_payload(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $members = collect(range(1, 51))->map(fn ($i) => [
            'first_name' => "Member{$i}",
            'last_name' => "Test{$i}",
        ])->all();

        $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/members/bulk-import', ['members' => $members])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['members']);
    }

    public function test_member_index_is_scoped_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        Tenant::factory()->has(Member::factory()->count(2))->create();

        Member::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/members');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_member_index_filters_by_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        Member::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'membership_status' => 'active',
        ]);

        Member::factory()->create([
            'tenant_id' => $tenant->id,
            'membership_status' => 'inactive',
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/members?status=active');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(2, $data);
        $this->assertTrue(collect($data)->every(fn ($member) => $member['membership_status'] === 'active'));
    }

    public function test_member_index_searches_by_name(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        Member::factory()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Eleanor',
            'last_name' => 'Shellstrop',
        ]);

        Member::factory()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Chidi',
            'last_name' => 'Anagonye',
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/members?search=elea');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Eleanor', $data[0]['first_name']);
    }

    public function test_member_index_supports_pagination_controls(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        Member::factory()->count(3)->sequence(
            ['first_name' => 'Anna', 'last_name' => 'Alpha', 'tenant_id' => $tenant->id],
            ['first_name' => 'Bella', 'last_name' => 'Bravo', 'tenant_id' => $tenant->id],
            ['first_name' => 'Cara', 'last_name' => 'Charlie', 'tenant_id' => $tenant->id],
        )->create();

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/members?sort=first_name&direction=asc&per_page=2&page=2');

        $response->assertOk();

        $data = $response->json('data');
        $meta = $response->json('meta');

        $this->assertCount(1, $data);
        $this->assertSame('Cara', $data[0]['first_name']);
        $this->assertSame(2, $meta['per_page']);
        $this->assertSame(2, $meta['current_page']);
        $this->assertSame(3, $meta['total']);
    }

    public function test_member_file_custom_field_metadata_is_persisted(): void
    {
        Storage::fake('custom_fields');

        $tenant = Tenant::factory()->create();
        $field = MemberCustomField::factory()->file()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAsTenantAdmin($tenant);

        $metadata = [
            'disk' => 'custom_fields',
            'path' => 'custom-fields/' . $tenant->id . '/' . $field->id . '/example.pdf',
            'name' => 'example.pdf',
            'mime' => 'application/pdf',
            'size' => 512,
        ];

        $payload = [
            'first_name' => 'File',
            'last_name' => 'Holder',
            'membership_status' => 'active',
            'custom_values' => [
                [
                    'field_id' => $field->id,
                    'value' => $metadata,
                ],
            ],
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/members', $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('member_custom_values', [
            'field_id' => $field->id,
            'value_file_path' => $metadata['path'],
            'value_file_name' => $metadata['name'],
        ]);
    }

    public function test_member_update_overwrites_contacts(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        MemberContact::factory()->forMember($member)->create([
            'type' => 'email',
            'value' => 'old@example.com',
            'is_primary' => true,
        ]);

        $payload = [
            'contacts' => [
                [
                    'type' => 'mobile',
                    'value' => '+15550002222',
                    'is_primary' => true,
                ],
            ],
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->putJson("/api/v1/members/{$member->uuid}", $payload);

        $response->assertOk();
        $this->assertDatabaseMissing('member_contacts', [
            'member_id' => $member->id,
            'type' => 'email',
        ]);
        $this->assertDatabaseHas('member_contacts', [
            'member_id' => $member->id,
            'type' => 'mobile',
        ]);
    }

    public function test_member_index_supports_sorting_and_includes_primary_contact(): void
    {
        $tenant = Tenant::factory()->create();

        [$first, $second] = Member::factory()
            ->count(2)
            ->sequence(
                ['first_name' => 'Abigail', 'last_name' => 'Zephyr', 'tenant_id' => $tenant->id],
                ['first_name' => 'Zion', 'last_name' => 'Andrews', 'tenant_id' => $tenant->id]
            )
            ->create();

        $this->actingAsTenantAdmin($tenant);

        MemberContact::factory()->forMember($first)->create([
            'type' => 'email',
            'value' => 'abigail@example.com',
            'is_primary' => true,
        ]);

        MemberContact::factory()->forMember($second)->create([
            'type' => 'email',
            'value' => 'zion@example.com',
            'is_primary' => true,
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/members?sort=first_name&direction=desc');

        $response->assertOk();

        $data = $response->json('data');

        $this->assertSame('Zion', $data[0]['first_name']);
        $this->assertSame('zion@example.com', $data[0]['preferred_contact']['value']);
        $this->assertArrayHasKey('membership_stage', $data[0]);
    }

    public function test_member_creation_is_audited(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantAdmin($tenant);

        $payload = Member::factory()->make(['tenant_id' => $tenant->id])->only([
            'first_name',
            'last_name',
            'membership_status',
        ]);

        $this->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/members', $payload)
            ->assertCreated();

        /** @var AuditLog|null $log */
        $log = AuditLog::query()->where('action', 'member.created')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($tenant->id, $log->tenant_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(Member::class, $log->auditable_type);
        $this->assertIsArray($log->payload);
        $this->assertSame($payload['first_name'], $log->payload['attributes']['first_name'] ?? null);
    }

    public function test_member_update_is_audited(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantAdmin($tenant);

        $member = Member::factory()->create([
            'tenant_id' => $tenant->id,
            'membership_status' => 'active',
        ]);

        $this->withHeader('X-Tenant-ID', $tenant->uuid)
            ->patchJson("/api/v1/members/{$member->uuid}", [
                'membership_status' => 'inactive',
            ])
            ->assertOk();

        /** @var AuditLog|null $log */
        $log = AuditLog::query()->where('action', 'member.updated')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($tenant->id, $log->tenant_id);
        $this->assertSame($member->id, $log->auditable_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(Member::class, $log->auditable_type);
        $member->refresh();
        $this->assertSame('inactive', $member->membership_status);
        $this->assertContains('membership_status', $log->payload['updated_fields'] ?? []);
    }

    public function test_soft_deleted_member_can_be_restored(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantAdmin($tenant);

        $member = Member::factory()->create([
            'tenant_id' => $tenant->id,
            'membership_status' => 'active',
        ]);

        $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->deleteJson("/api/v1/members/{$member->uuid}")
            ->assertNoContent();

        $deletedMember = Member::withTrashed()->find($member->id);
        $this->assertNotNull($deletedMember);
        $this->assertSoftDeleted('members', ['id' => $deletedMember->id]);

        $restoreResponse = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson("/api/v1/members/{$member->uuid}/restore");

        $restoreResponse
            ->assertOk()
            ->assertJsonPath('data.uuid', $member->uuid);

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'deleted_at' => null,
        ]);

        /** @var AuditLog|null $log */
        $log = AuditLog::query()->where('action', 'member.restored')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($tenant->id, $log->tenant_id);
        $this->assertSame($member->id, $log->auditable_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertTrue($log->payload['restored'] ?? false);
    }
}
