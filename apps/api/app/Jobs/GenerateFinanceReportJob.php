<?php

namespace App\Jobs;

use App\Jobs\Concerns\DispatchesForTenant;
use App\Models\FinanceReport;
use App\Models\FinancialLedgerEntry;
use App\Models\Member;
use App\Models\Tenant;
use App\Support\Branding\TenantBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

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

            $branding = TenantBranding::resolve($tenant);

            [$html, $filename] = $this->renderReport($tenant, $report, $branding);

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

    protected function renderReport(Tenant $tenant, FinanceReport $report, array $branding): array
    {
        $filters = $report->filters ?? [];

        return match ($report->type) {
            'donations' => $this->renderDonations($tenant, $filters, $branding),
            'pledges' => $this->renderPledges($tenant, $branding),
            'monthly-statement' => $this->renderMonthlyStatement($tenant, $filters, $branding),
            'balance-sheet' => $this->renderBalanceSheet($tenant, $filters, $branding),
            'donor-letters' => $this->renderDonorLetters($tenant, $filters, $branding),
            default => $this->renderDonorStatement($tenant, $filters, $branding),
        };
    }

    protected function renderDonations(Tenant $tenant, array $filters, array $branding): array
    {
        $query = $tenant->donations()->with(['member', 'items.fund'])->orderByDesc('received_at');

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
            'branding' => $branding,
            'title' => 'Donations summary',
            'generated_at' => Carbon::now(),
        ])->render();

        return [$html, 'donations.pdf'];
    }

    protected function renderPledges(Tenant $tenant, array $branding): array
    {
        $pledges = $tenant->pledges()->with(['member', 'fund'])->orderByDesc('created_at')->get();

        $html = view('reports.finance.pledges', [
            'tenant' => $tenant,
            'pledges' => $pledges,
            'branding' => $branding,
            'title' => 'Pledges summary',
            'generated_at' => Carbon::now(),
        ])->render();

        return [$html, 'pledges.pdf'];
    }

    protected function renderDonorStatement(Tenant $tenant, array $filters, array $branding): array
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
            'branding' => $branding,
            'title' => 'Donor statement',
            'generated_at' => Carbon::now(),
        ])->render();

        return [$html, 'donor-statement-' . $member->id . '.pdf'];
    }

    protected function renderMonthlyStatement(Tenant $tenant, array $filters, array $branding): array
    {
        $month = Arr::get($filters, 'month');
        if ($month) {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $end = $start->copy()->endOfMonth();
        } else {
            $start = Arr::has($filters, 'from') ? Carbon::parse($filters['from'])->startOfDay() : Carbon::now()->startOfMonth();
            $end = Arr::has($filters, 'to') ? Carbon::parse($filters['to'])->endOfDay() : $start->copy()->endOfMonth();
        }

        $donations = $tenant->donations()
            ->with(['member', 'items.fund'])
            ->whereBetween('received_at', [$start, $end])
            ->orderBy('received_at')
            ->get();

        $dailyTotals = $donations->groupBy(fn ($donation) => $donation->received_at?->format('Y-m-d'))
            ->map(fn ($group) => $group->sum('amount'))
            ->sortKeys();

        $fundTotals = $donations
            ->flatMap(fn ($donation) => $donation->items)
            ->groupBy('fund_id')
            ->map(function ($items) {
                $fund = $items->first()->fund;
                return [
                    'fund_name' => $fund?->name ?? 'Unassigned',
                    'total' => $items->sum('amount'),
                ];
            })
            ->values();

        $html = view('reports.finance.monthly-statement', [
            'tenant' => $tenant,
            'branding' => $branding,
            'title' => sprintf('Monthly statement — %s', $start->format('F Y')),
            'generated_at' => Carbon::now(),
            'date_range' => [$start, $end],
            'donations' => $donations,
            'daily_totals' => $dailyTotals,
            'fund_totals' => $fundTotals,
        ])->render();

        return [$html, 'monthly-statement-' . $start->format('Y-m') . '.pdf'];
    }

    protected function renderBalanceSheet(Tenant $tenant, array $filters, array $branding): array
    {
        $start = Arr::has($filters, 'from') ? Carbon::parse($filters['from'])->startOfDay() : null;
        $end = Arr::has($filters, 'to') ? Carbon::parse($filters['to'])->endOfDay() : null;

        $entriesQuery = FinancialLedgerEntry::query()
            ->with('financialAccount')
            ->where('tenant_id', $tenant->id);

        if ($start) {
            $entriesQuery->where('occurred_at', '>=', $start);
        }
        if ($end) {
            $entriesQuery->where('occurred_at', '<=', $end);
        }

        $entries = $entriesQuery->get();

        $byAccount = $entries->groupBy(fn ($entry) => $entry->financialAccount?->code ?? $entry->account)
            ->map(function ($group) {
                $account = $group->first()->financialAccount;
                $debits = $group->where('entry_type', 'debit')->sum('amount');
                $credits = $group->where('entry_type', 'credit')->sum('amount');

                return [
                    'code' => $account?->code ?? 'unknown',
                    'name' => $account?->name ?? $group->first()->account,
                    'type' => $account?->type ?? 'other',
                    'debits' => $debits,
                    'credits' => $credits,
                    'balance' => $debits - $credits,
                ];
            });

        $totalsByType = $byAccount->groupBy('type')->map(function ($group) {
            return [
                'debits' => $group->sum('debits'),
                'credits' => $group->sum('credits'),
                'balance' => $group->sum('balance'),
            ];
        });

        $html = view('reports.finance.balance-sheet', [
            'tenant' => $tenant,
            'branding' => $branding,
            'title' => 'Balance sheet',
            'generated_at' => Carbon::now(),
            'entries' => $byAccount->values(),
            'totals_by_type' => $totalsByType,
            'filters' => $filters,
        ])->render();

        return [$html, 'balance-sheet.pdf'];
    }

    protected function renderDonorLetters(Tenant $tenant, array $filters, array $branding): array
    {
        $start = Arr::has($filters, 'from') ? Carbon::parse($filters['from'])->startOfDay() : Carbon::now()->startOfYear();
        $end = Arr::has($filters, 'to') ? Carbon::parse($filters['to'])->endOfDay() : Carbon::now();

        $donors = Member::query()
            ->with(['donations' => function ($query) use ($start, $end) {
                $query->whereBetween('received_at', [$start, $end])->orderBy('received_at');
            }])
            ->where('tenant_id', $tenant->id)
            ->whereHas('donations', fn ($query) => $query->whereBetween('received_at', [$start, $end]))
            ->orderBy('last_name')
            ->get();

        $html = view('reports.finance.donor-letters', [
            'tenant' => $tenant,
            'branding' => $branding,
            'title' => 'Donor appreciation letters',
            'generated_at' => Carbon::now(),
            'donors' => $donors,
            'date_range' => [$start, $end],
        ])->render();

        return [$html, 'donor-letters.pdf'];
    }
}
