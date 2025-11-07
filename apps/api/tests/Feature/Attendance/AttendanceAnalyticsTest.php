<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Gathering;
use App\Models\Member;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_analytics_returns_metrics(): void
    {
        [$tenant, $recentGathering, $memberAbsent] = $this->seedAttendanceAnalyticsFixtures();

        $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->getJson('/api/v1/attendance/analytics');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => ['gatherings_tracked', 'total_check_ins', 'attendance_rate', 'average_present'],
                    'trend',
                    'top_gatherings',
                    'followup_candidates',
                ],
            ]);

        $summary = $response->json('data.summary');
        $this->assertSame(2, $summary['gatherings_tracked']);
        $this->assertSame(2, $summary['total_check_ins']);
        $this->assertGreaterThan(0, $summary['attendance_rate']);

        $topGatherings = $response->json('data.top_gatherings');
        $this->assertNotEmpty($topGatherings);
        $this->assertSame($recentGathering->id, $topGatherings[0]['id']);

        $followups = $response->json('data.followup_candidates');
        $this->assertNotEmpty($followups);
        $this->assertSame($memberAbsent->id, $followups[0]['member_id']);
        $this->assertSame(2, $followups[0]['absent']);

        $serviceBreakdown = $response->json('data.service_breakdown');
        $this->assertNotEmpty($serviceBreakdown);
        $this->assertSame($recentGathering->service_id, $serviceBreakdown[0]['service_id']);

        $memberSegments = $response->json('data.member_segments');
        $this->assertNotEmpty($memberSegments);
        $this->assertArrayHasKey('label', $memberSegments[0]);

        $departmentSegments = $response->json('data.department_segments');
        $this->assertNotEmpty($departmentSegments);
        $this->assertArrayHasKey('label', $departmentSegments[0]);

        $ageBands = $response->json('data.age_bands');
        $this->assertNotEmpty($ageBands);
        $this->assertArrayHasKey('label', $ageBands[0]);
    }

    public function test_attendance_analytics_respects_filters(): void
    {
        [$tenant, $recentGathering] = $this->seedAttendanceAnalyticsFixtures();

        $otherService = Service::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $filteredGathering = Gathering::factory()->create([
            'tenant_id' => $tenant->id,
            'service_id' => $otherService->id,
            'status' => 'completed',
            'starts_at' => now()->subDay()->setTime(9, 0),
        ]);

        $member = Member::factory()->create(['tenant_id' => $tenant->id]);

        AttendanceRecord::factory()
            ->forGathering($filteredGathering)
            ->forMember($member)
            ->state([
                'status' => 'present',
                'checked_in_at' => now()->subDay()->setTime(9, 15),
            ])
            ->create();

        $this->actingAsTenantAdmin($tenant);

        $query = http_build_query([
            'service_id' => $otherService->id,
            'status' => 'completed',
            'from' => now()->subDays(2)->toDateString(),
            'to' => now()->toDateString(),
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->getJson('/api/v1/attendance/analytics?' . $query);

        $response->assertOk();

        $summary = $response->json('data.summary');
        $this->assertSame(1, $summary['gatherings_tracked']);
        $this->assertSame(1, $summary['total_check_ins']);

        $topGatherings = $response->json('data.top_gatherings');
        $this->assertCount(1, $topGatherings);
        $this->assertSame($filteredGathering->id, $topGatherings[0]['id']);

        $followups = $response->json('data.followup_candidates');
        $this->assertEmpty($followups, 'Filtered attendance should not include earlier absences');

        $serviceBreakdown = $response->json('data.service_breakdown');
        $this->assertCount(1, $serviceBreakdown);
        $this->assertSame($otherService->id, $serviceBreakdown[0]['service_id']);

        $memberSegments = $response->json('data.member_segments');
        $this->assertNotEmpty($memberSegments);

        $departmentSegments = $response->json('data.department_segments');
        $this->assertNotEmpty($departmentSegments);

        $ageBands = $response->json('data.age_bands');
        $this->assertNotEmpty($ageBands);
    }

    public function test_attendance_analytics_export_streams_csv(): void
    {
        [$tenant, $recentGathering] = $this->seedAttendanceAnalyticsFixtures();

        $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->get('/api/v1/attendance/analytics/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Gathering', $content);
        $this->assertStringContainsString($recentGathering->name, $content);
    }

    public function test_attendance_analytics_combined_filters_limit_results_and_exports(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 15, 10));

        $tenant = Tenant::factory()->create();
        $serviceMatch = Service::factory()->create(['tenant_id' => $tenant->id]);
        $serviceOther = Service::factory()->create(['tenant_id' => $tenant->id]);

        $matchingGathering = Gathering::factory()->create([
            'tenant_id' => $tenant->id,
            'service_id' => $serviceMatch->id,
            'status' => 'completed',
            'starts_at' => now()->subDays(3)->setTime(9, 0),
        ]);

        $otherServiceGathering = Gathering::factory()->create([
            'tenant_id' => $tenant->id,
            'service_id' => $serviceOther->id,
            'status' => 'completed',
            'starts_at' => now()->subDays(2)->setTime(11, 0),
        ]);

        $otherStatusGathering = Gathering::factory()->create([
            'tenant_id' => $tenant->id,
            'service_id' => $serviceMatch->id,
            'status' => 'scheduled',
            'starts_at' => now()->subDay()->setTime(15, 0),
        ]);

        $outOfRangeGathering = Gathering::factory()->create([
            'tenant_id' => $tenant->id,
            'service_id' => $serviceMatch->id,
            'status' => 'completed',
            'starts_at' => now()->subMonths(2)->setTime(10, 0),
        ]);

        $memberOne = Member::factory()->create(['tenant_id' => $tenant->id]);
        $memberTwo = Member::factory()->create(['tenant_id' => $tenant->id]);
        $memberAbsent = Member::factory()->create(['tenant_id' => $tenant->id]);

        AttendanceRecord::factory()
            ->forGathering($matchingGathering)
            ->forMember($memberOne)
            ->state([
                'status' => 'present',
                'checked_in_at' => $matchingGathering->starts_at,
            ])
            ->create();

        AttendanceRecord::factory()
            ->forGathering($matchingGathering)
            ->forMember($memberTwo)
            ->state([
                'status' => 'present',
                'checked_in_at' => $matchingGathering->starts_at->copy()->addMinutes(15),
            ])
            ->create();

        AttendanceRecord::factory()
            ->forGathering($matchingGathering)
            ->forMember($memberAbsent)
            ->state([
                'status' => 'absent',
                'checked_in_at' => null,
            ])
            ->create();

        AttendanceRecord::factory()
            ->forGathering($otherServiceGathering)
            ->forMember($memberOne)
            ->state([
                'status' => 'present',
                'checked_in_at' => $otherServiceGathering->starts_at,
            ])
            ->create();

        AttendanceRecord::factory()
            ->forGathering($otherStatusGathering)
            ->forMember($memberOne)
            ->state([
                'status' => 'present',
                'checked_in_at' => $otherStatusGathering->starts_at,
            ])
            ->create();

        AttendanceRecord::factory()
            ->forGathering($outOfRangeGathering)
            ->forMember($memberTwo)
            ->state([
                'status' => 'present',
                'checked_in_at' => $outOfRangeGathering->starts_at,
            ])
            ->create();

        $this->actingAsTenantAdmin($tenant);

        $query = http_build_query([
            'service_id' => $serviceMatch->id,
            'status' => 'completed',
            'from' => now()->subDays(5)->toDateString(),
            'to' => now()->toDateString(),
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->getJson('/api/v1/attendance/analytics?' . $query);

        $response->assertOk();

        $summary = $response->json('data.summary');
        $this->assertSame(1, $summary['gatherings_tracked']);
        $this->assertSame(2, $summary['total_check_ins']);

        $topGatherings = $response->json('data.top_gatherings');
        $this->assertCount(1, $topGatherings);
        $this->assertSame($matchingGathering->id, $topGatherings[0]['id']);

        $trend = collect($response->json('data.trend'));
        $this->assertTrue($trend->contains(fn ($bucket) => $bucket['present'] === 2));
        $this->assertFalse($trend->contains(fn ($bucket) => $bucket['present'] > 2));

        $exportResponse = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->get('/api/v1/attendance/analytics/export?' . $query);

        $exportResponse->assertOk();
        $csv = $exportResponse->streamedContent();
        $this->assertStringContainsString($matchingGathering->name, $csv);
        $this->assertStringNotContainsString($otherServiceGathering->name, $csv);
        $this->assertStringNotContainsString($otherStatusGathering->name, $csv);
        $this->assertStringNotContainsString($outOfRangeGathering->name, $csv);

        $serviceBreakdown = $response->json('data.service_breakdown');
        $this->assertCount(1, $serviceBreakdown);
        $this->assertSame($serviceMatch->id, $serviceBreakdown[0]['service_id']);

        $memberSegments = $response->json('data.member_segments');
        $this->assertNotEmpty($memberSegments);
        $this->assertArrayHasKey('members', $memberSegments[0]);

        $departmentSegments = $response->json('data.department_segments');
        $this->assertNotEmpty($departmentSegments);

        $ageBands = $response->json('data.age_bands');
        $this->assertNotEmpty($ageBands);

        Carbon::setTestNow();
    }

    public function test_bulk_attendance_export_returns_zip(): void
    {
        [$tenant, $recentGathering] = $this->seedAttendanceAnalyticsFixtures();

        $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->post('/api/v1/attendance/exports', [
                'gathering_ids' => [$recentGathering->id],
                'format' => 'csv',
            ]);

        $response->assertOk();
        $this->assertStringContainsString('application/zip', (string) $response->headers->get('content-type'));

        $content = $response->getContent();
        $tempZip = tempnam(sys_get_temp_dir(), 'attendance-export-test-');
        file_put_contents($tempZip, $content);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tempZip) === true);
        $this->assertSame(1, $zip->numFiles);
        $zip->close();

        unlink($tempZip);
    }

    /**
     * @return array{Tenant, Gathering, Member}
     */
    private function seedAttendanceAnalyticsFixtures(): array
    {
        $tenant = Tenant::factory()->create();
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        /** @var Gathering $recentGathering */
        $recentGathering = Gathering::factory()->create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'status' => 'completed',
            'starts_at' => now()->subWeek()->startOfDay()->addHours(10),
        ]);

        $memberPresentOne = Member::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $memberPresentTwo = Member::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $memberAbsent = Member::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        AttendanceRecord::factory()
            ->forGathering($recentGathering)
            ->forMember($memberPresentOne)
            ->state([
                'status' => 'present',
                'checked_in_at' => now()->subWeek()->startOfDay()->addHours(10),
            ])
            ->create();

        AttendanceRecord::factory()
            ->forGathering($recentGathering)
            ->forMember($memberPresentTwo)
            ->state([
                'status' => 'present',
                'checked_in_at' => now()->subWeek()->startOfDay()->addHours(11),
            ])
            ->create();

        /** @var Gathering $anotherGathering */
        $anotherGathering = Gathering::factory()->create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'status' => 'completed',
            'starts_at' => now()->subDays(10)->startOfDay()->addHours(9),
        ]);

        AttendanceRecord::factory()
            ->forGathering($recentGathering)
            ->forMember($memberAbsent)
            ->state([
                'status' => 'absent',
                'checked_in_at' => null,
                'created_at' => now()->subWeek()->startOfDay()->addHours(12),
            ])
            ->create();

        AttendanceRecord::factory()
            ->forGathering($anotherGathering)
            ->forMember($memberAbsent)
            ->state([
                'status' => 'absent',
                'checked_in_at' => null,
                'created_at' => now()->subDays(10)->startOfDay()->addHours(11),
            ])
            ->create();

        $oldGathering = Gathering::factory()->create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'status' => 'completed',
            'starts_at' => now()->subMonths(3),
        ]);

        AttendanceRecord::factory()
            ->forGathering($oldGathering)
            ->forMember($memberPresentOne)
            ->state([
                'status' => 'present',
                'checked_in_at' => now()->subMonths(3),
            ])
            ->create();

        return [$tenant, $recentGathering, $memberAbsent];
    }
}
