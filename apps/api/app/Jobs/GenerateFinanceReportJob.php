<?php

namespace App\Jobs;

use App\Jobs\Concerns\DispatchesForTenant;
use App\Models\FinanceReport;
use App\Models\Member;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;

class GenerateFinanceReportJob implements ShouldQueue
{
    use DispatchesForTenant;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $disk = 'reports';

    public function __construct(private readonly int $reportId)
    {
    }

    public function handle(): void
    {
        $report = FinanceReport::query()->findOrFail($this->reportId);

        try {
            $report->update(['status' => 'processing']);

            $tenant = Tenant::query()->findOrFail($report->tenant_id);
            $config = config('reports.pdf_config', [
                'tempDir' => storage_path('app/reports/tmp'),
            ]);

            if (!is_dir($config['tempDir'])) {
                @mkdir($config['tempDir'], 0755, true);
            }

            $mpdf = new Mpdf($config);

            [$html, $filename] = match ($report->type) {
                'donations' => $this->renderDonations($tenant, $report->filters ?? []),
                'pledges' => $this->renderPledges($tenant),
                default => $this->renderDonorStatement($tenant, $report->filters ?? []),
            };

            $mpdf->WriteHTML($html);
            $path = trim(config('reports.directory', 'finance'), '/') . '/' . Str::uuid() . '-' . $filename;
            Storage::disk($this->disk)->put($path, $mpdf->OutputBinaryData());

            $report->update([
                'status' => 'completed',
                'disk' => $this->disk,
                'file_path' => $path,
                'generated_at' => now(),
            ]);
        } catch (MpdfException $exception) {
            $report->update([
                'status' => 'failed',
                'failure_reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    protected function renderDonations(Tenant $tenant, array $filters): array
    {
        $query = $tenant->donations()->with(['member'])->orderByDesc('received_at');

        if (! empty($filters['from'])) {
            $query->whereDate('received_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('received_at', '<=', $filters['to']);
        }

        $donations = $query->get();

        $html = view('reports.finance.donations', [
            'tenant' => $tenant,
            'donations' => $donations,
            'filters' => $filters,
        ])->render();

        return [$html, 'donations.pdf'];
    }

    protected function renderPledges(Tenant $tenant): array
    {
        $pledges = $tenant->pledges()->with(['member', 'fund'])->orderByDesc('created_at')->get();

        $html = view('reports.finance.pledges', [
            'tenant' => $tenant,
            'pledges' => $pledges,
        ])->render();

        return [$html, 'pledges.pdf'];
    }

    protected function renderDonorStatement(Tenant $tenant, array $filters): array
    {
        $member = Member::query()->where('tenant_id', $tenant->id)->findOrFail($filters['member_id']);

        $donations = $member->donations()
            ->whereBetween('received_at', [
                $filters['from'] ?? now()->startOfYear(),
                $filters['to'] ?? now(),
            ])
            ->orderBy('received_at')
            ->get();

        $html = view('reports.finance.donor-statement', [
            'tenant' => $tenant,
            'member' => $member,
            'donations' => $donations,
            'filters' => $filters,
        ])->render();

        return [$html, 'donor-statement-' . $member->id . '.pdf'];
    }
}
