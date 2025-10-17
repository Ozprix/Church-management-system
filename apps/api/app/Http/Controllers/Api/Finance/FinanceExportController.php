<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\Member;
use App\Models\Pledge;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FinanceExportController extends Controller
{
    public function __construct()
    {
        $this->middleware('feature:finance');
        $this->middleware('can:finance.export');
    }

    public function donations(Request $request)
    {
        $tenant = $request->attributes->get('tenant');
        $query = Donation::query()
            ->with(['member'])
            ->orderByDesc('received_at');

        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }

        if ($request->filled('from')) {
            $query->whereDate('received_at', '>=', Carbon::parse($request->input('from')));
        }

        if ($request->filled('to')) {
            $query->whereDate('received_at', '<=', Carbon::parse($request->input('to')));
        }

        $filename = 'donations-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Member', 'Amount', 'Currency', 'Status', 'Provider', 'Reference']);

            $query->chunk(500, function ($donations) use ($handle) {
                foreach ($donations as $donation) {
                    $memberName = 'Guest';
                    if ($donation->member) {
                        $memberName = trim(($donation->member->first_name ?? '') . ' ' . ($donation->member->last_name ?? '')) ?: $donation->member->id;
                    }

                    fputcsv($handle, [
                        optional($donation->received_at)->toDateString(),
                        $memberName,
                        $donation->amount,
                        $donation->currency,
                        $donation->status,
                        $donation->provider,
                        $donation->provider_reference,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function pledges(Request $request)
    {
        $tenant = $request->attributes->get('tenant');
        $query = Pledge::query()->with(['member', 'fund'])->orderByDesc('created_at');

        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }

        $filename = 'pledges-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Member', 'Fund', 'Amount', 'Fulfilled', 'Currency', 'Frequency', 'Status', 'Start', 'End']);

            $query->chunk(500, function ($pledges) use ($handle) {
                foreach ($pledges as $pledge) {
                    $memberName = 'Guest';
                    if ($pledge->member) {
                        $memberName = trim(($pledge->member->first_name ?? '') . ' ' . ($pledge->member->last_name ?? '')) ?: $pledge->member->id;
                    }

                    fputcsv($handle, [
                        $memberName,
                        $pledge->fund?->name,
                        $pledge->amount,
                        $pledge->fulfilled_amount,
                        $pledge->currency,
                        $pledge->frequency,
                        $pledge->status,
                        optional($pledge->start_date)->toDateString(),
                        optional($pledge->end_date)->toDateString(),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function donorStatement(Request $request, Member $member)
    {
        $tenant = $request->attributes->get('tenant');
        if ($tenant && $member->tenant_id !== $tenant->id) {
            abort(404);
        }

        $from = $request->input('from') ? Carbon::parse($request->input('from')) : Carbon::now()->startOfYear();
        $to = $request->input('to') ? Carbon::parse($request->input('to')) : Carbon::now();

        $donations = Donation::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->whereBetween('received_at', [$from, $to])
            ->orderBy('received_at')
            ->get();

        $filename = sprintf('donor-statement-%s-%s.csv', $member->id, now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($donations, $member, $from, $to): void {
            $handle = fopen('php://output', 'w');
            $memberName = trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')) ?: (string) $member->id;
            fputcsv($handle, ['Donor', $memberName]);
            fputcsv($handle, ['Period', $from->toDateString() . ' - ' . $to->toDateString()]);
            fputcsv($handle, []);
            fputcsv($handle, ['Date', 'Amount', 'Currency', 'Status', 'Reference']);

            foreach ($donations as $donation) {
                fputcsv($handle, [
                    optional($donation->received_at)->toDateString(),
                    $donation->amount,
                    $donation->currency,
                    $donation->status,
                    $donation->provider_reference,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
