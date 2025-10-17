<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Services\Analytics\FinanceAnalyticsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceAnalyticsExportController extends Controller
{
    public function __construct(private readonly FinanceAnalyticsService $analyticsService)
    {
    }

    public function __invoke(Request $request): StreamedResponse
    {
        $this->authorizePermission('finance.view');

        $filters = $this->analyticsService->parseFilters($request->query());
        $query = $this->analyticsService->applyDonationFilters(
            Donation::query()->with(['member', 'items.fund']),
            $filters
        )->orderByDesc('received_at');

        $filename = 'finance-analytics-' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($query): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'Received at',
                'Amount',
                'Status',
                'Member',
                'Funds',
            ]);

            $query->chunk(200, function ($donations) use ($handle): void {
                foreach ($donations as $donation) {
                    $funds = $donation->items
                        ->map(fn ($item) => $item->fund?->name ?? 'Unassigned')
                        ->unique()
                        ->implode(', ');

                    $memberName = $donation->member
                        ? trim($donation->member->first_name . ' ' . $donation->member->last_name)
                        : 'Anonymous';

                    fputcsv($handle, [
                        optional($donation->received_at)->toDateString(),
                        (float) $donation->amount,
                        $donation->status,
                        $memberName,
                        $funds,
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
