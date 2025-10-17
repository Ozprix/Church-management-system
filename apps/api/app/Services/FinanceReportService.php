<?php

namespace App\Services;

use App\Jobs\GenerateFinanceReportJob;
use App\Models\FinanceReport;
use App\Support\Tenancy\TenantManager;
use Illuminate\Support\Facades\Auth;

class FinanceReportService
{
    public function __construct(private readonly TenantManager $tenantManager)
    {
    }

    public function create(string $type, array $filters = []): FinanceReport
    {
        $tenant = $this->tenantManager->getTenant();
        $user = Auth::user();

        $report = FinanceReport::create([
            'tenant_id' => $tenant?->id ?? $user?->tenant_id,
            'requested_by' => $user?->id,
            'type' => $type,
            'status' => 'pending',
            'filters' => $filters ?: null,
        ]);

        GenerateFinanceReportJob::dispatch($report->id);

        return $report->fresh();
    }
}
