<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Jobs\GenerateFinanceReportJob;
use App\Models\Donation;
use App\Models\DonationItem;
use App\Models\FinanceReport;
use App\Models\Fund;
use App\Models\Member;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinanceReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_monthly_statement(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/finance/reports', [
                'type' => 'monthly-statement',
                'filters' => [
                    'month' => '2024-03',
                ],
            ]);

        $response->assertAccepted();
        $response->assertJsonPath('data.type', 'monthly-statement');
        $status = $response->json('data.status');
        $this->assertContains($status, ['pending', 'processing', 'completed']);
    }

    public function test_donor_statement_requires_member(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/finance/reports', [
                'type' => 'donor-statement',
                'filters' => [],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['filters.member_id']);
    }

    public function test_generate_finance_report_job_creates_pdf_with_branding(): void
    {
        Storage::fake('reports');

        $tenant = Tenant::factory()->create([
            'meta' => [
                'branding' => [
                    'logo_url' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="64" height="64" fill="#0f172a"/></svg>'),
                    'primary_color' => '#0f172a',
                    'accent_color' => '#16a34a',
                    'address' => '123 Example Street',
                ],
            ],
        ]);

        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);

        $donation = Donation::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'status' => 'succeeded',
            'amount' => 125.00,
            'currency' => 'USD',
            'received_at' => Carbon::parse('2024-03-05 10:00:00'),
        ]);

        DonationItem::factory()->create([
            'donation_id' => $donation->id,
            'fund_id' => $fund->id,
            'amount' => 125.00,
        ]);

        /** @var FinanceReport $report */
        $report = FinanceReport::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => 'monthly-statement',
            'status' => 'pending',
            'filters' => ['month' => '2024-03'],
            'disk' => 'reports',
        ]);

        // The job runs synchronously here to make assertions deterministic.
        (new GenerateFinanceReportJob($report->id))->handle();

        $report->refresh();

        $this->assertSame('completed', $report->status);
        $this->assertNotNull($report->file_path);
        Storage::disk('reports')->assertExists($report->file_path);
    }
}
