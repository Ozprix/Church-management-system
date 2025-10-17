<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Gathering;
use App\Models\Member;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatheringApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_schedules_gathering_and_returns_resource(): void
    {
        $tenant = Tenant::factory()->create();
        $service = Service::factory()->forTenant($tenant)->create();

        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'name' => 'Sunday 9AM Service',
            'service_id' => $service->id,
            'starts_at' => now()->addWeek()->setHour(9)->setMinute(0)->toIso8601String(),
            'location' => 'Sanctuary',
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/gatherings', $payload);

        $response->assertCreated()->assertJsonPath('data.name', 'Sunday 9AM Service');

        $this->assertDatabaseHas('gatherings', [
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'name' => 'Sunday 9AM Service',
        ]);
    }

    public function test_it_records_attendance_and_returns_counts(): void
    {
        $tenant = Tenant::factory()->create();
        $service = Service::factory()->forTenant($tenant)->create();
        $gathering = Gathering::factory()->forService($service)->create([
            'starts_at' => now()->subDay(),
        ]);
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson("/api/v1/gatherings/{$gathering->uuid}/attendance", [
                'member_id' => $member->id,
                'status' => 'present',
                'check_in_method' => 'qr',
            ])
            ->assertCreated();

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson("/api/v1/gatherings/{$gathering->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.attendance.total', 1)
            ->assertJsonPath('data.attendance.present', 1)
            ->assertJsonPath('data.attendance_records.0.status', 'present');
    }

    public function test_it_bulk_marks_absent_members(): void
    {
        $tenant = Tenant::factory()->create();
        $service = Service::factory()->forTenant($tenant)->create();
        $gathering = Gathering::factory()->forService($service)->create();
        $members = Member::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        $memberIds = $members->pluck('id')->all();

        $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson("/api/v1/gatherings/{$gathering->uuid}/attendance/bulk", [
                'member_ids' => $memberIds,
                'status' => 'absent',
            ])
            ->assertOk();

        foreach ($memberIds as $memberId) {
            $this->assertDatabaseHas('attendance_records', [
                'gathering_id' => $gathering->id,
                'member_id' => $memberId,
                'status' => 'absent',
            ]);
        }
    }
}
