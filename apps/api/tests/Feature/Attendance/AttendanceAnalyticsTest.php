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
