<?php

namespace App\Services\Analytics;

use App\Models\Donation;
use App\Models\DonationItem;
use App\Models\Member;
use App\Models\Pledge;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinanceAnalyticsService
{
    /**
     * @return array{
     *     status?: string|null,
     *     fund_id?: int|null,
     *     date_from?: ?Carbon,
     *     date_to?: ?Carbon
     * }
     */
    public function parseFilters(array $query): array
    {
        $status = $query['status'] ?? null;
        $fundId = isset($query['fund_id']) ? (int) $query['fund_id'] : null;

        $dateFrom = null;
        if (! empty($query['date_from'])) {
            $dateFrom = Carbon::make($query['date_from'])?->startOfDay();
        }

        $dateTo = null;
        if (! empty($query['date_to'])) {
            $dateTo = Carbon::make($query['date_to'])?->endOfDay();
        }

        return [
            'status' => $status !== '' ? $status : null,
            'fund_id' => $fundId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    public function applyDonationFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($filters['date_from'] instanceof Carbon) {
            $query->where('received_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] instanceof Carbon) {
            $query->where('received_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['fund_id'])) {
            $query->whereHas('items', fn ($inner) => $inner->where('fund_id', $filters['fund_id']));
        }

        return $query;
    }

    public function buildMetrics(array $filters): array
    {
        $donationQuery = $this->applyDonationFilters(Donation::query(), $filters);

        $succeededQuery = (clone $donationQuery)->where('status', 'succeeded');
        $totalDonationsAmount = (clone $succeededQuery)->sum('amount');
        $averageDonation = (clone $succeededQuery)->avg('amount') ?? 0.0;

        $monthStart = now()->startOfMonth();
        $monthTotal = (clone $succeededQuery)->where('received_at', '>=', $monthStart)->sum('amount');

        $activePledges = $this->applyPledgeFilters(Pledge::query(), $filters)
            ->where('status', 'active')
            ->count();

        $statusBreakdown = $this->statusBreakdown((clone $donationQuery));
        $fundBreakdown = $this->fundBreakdown($filters);
        $monthlyTrend = $this->monthlyTrend($filters);
        $topDonors = $this->topDonors($filters);
        $recentDonations = $this->recentDonations($filters);

        return [
            'totals' => [
                'donations_amount' => (float) $totalDonationsAmount,
                'donations_this_month' => (float) $monthTotal,
                'average_donation' => round($averageDonation, 2),
                'active_pledges' => $activePledges,
            ],
            'by_status' => $statusBreakdown,
            'by_fund' => $fundBreakdown,
            'donations_trend' => $monthlyTrend,
            'top_donors' => $topDonors,
            'recent_donations' => $recentDonations,
        ];
    }

    protected function applyPledgeFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['fund_id'])) {
            $query->where('fund_id', $filters['fund_id']);
        }

        if ($filters['date_from'] instanceof Carbon) {
            $query->where(function ($inner) use ($filters) {
                $inner->whereNull('start_date')
                    ->orWhere('start_date', '>=', $filters['date_from']);
            });
        }

        if ($filters['date_to'] instanceof Carbon) {
            $query->where(function ($inner) use ($filters) {
                $inner->whereNull('end_date')
                    ->orWhere('end_date', '<=', $filters['date_to']);
            });
        }

        return $query;
    }

    protected function statusBreakdown(Builder $donationQuery): array
    {
        return $donationQuery
            ->select('status')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(amount) as total_amount')
            ->groupBy('status')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status ?? 'unknown',
                'count' => (int) $row->total_count,
                'amount' => (float) $row->total_amount,
            ])
            ->values()
            ->all();
    }

    protected function fundBreakdown(array $filters): array
    {
        return DonationItem::query()
            ->select('fund_id')
            ->selectRaw('SUM(amount) as total_amount')
            ->with('fund')
            ->whereHas('donation', fn ($query) => $this->applyDonationFilters($query, $filters)->where('status', 'succeeded'))
            ->groupBy('fund_id')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn (DonationItem $item) => [
                'fund_id' => $item->fund_id,
                'fund_name' => $item->fund?->name ?? 'Unassigned',
                'amount' => (float) $item->total_amount,
            ])
            ->values()
            ->all();
    }

    protected function monthlyTrend(array $filters): array
    {
        $start = now()->startOfMonth()->subMonths(5);
        $buckets = [];

        for ($i = 0; $i < 6; $i++) {
            $month = (clone $start)->addMonths($i);
            $buckets[$month->format('Y-m')] = [
                'label' => $month->format('M Y'),
                'value' => 0.0,
            ];
        }

        $this->applyDonationFilters(
            Donation::query()->where('status', 'succeeded')->where('received_at', '>=', $start),
            $filters
        )
            ->select(['amount', 'received_at'])
            ->orderBy('received_at')
            ->chunk(200, function (Collection $chunk) use (&$buckets, $start): void {
                foreach ($chunk as $donation) {
                    if (! $donation->received_at) {
                        continue;
                    }

                    $bucket = $donation->received_at->copy()->startOfMonth();
                    if ($bucket->lt($start)) {
                        $bucket = $start->copy();
                    }

                    $key = $bucket->format('Y-m');
                    if (! isset($buckets[$key])) {
                        $buckets[$key] = [
                            'label' => $bucket->format('M Y'),
                            'value' => 0.0,
                        ];
                    }

                    $buckets[$key]['value'] += (float) $donation->amount;
                }
            });

        return array_values($buckets);
    }

    protected function topDonors(array $filters): array
    {
        return $this->applyDonationFilters(Donation::query()->whereNotNull('member_id')->where('status', 'succeeded'), $filters)
            ->select('member_id')
            ->selectRaw('SUM(amount) as total_amount')
            ->groupBy('member_id')
            ->orderByDesc('total_amount')
            ->with('member')
            ->limit(5)
            ->get()
            ->map(function (Donation $donation) {
                /** @var Member|null $member */
                $member = $donation->member;

                return [
                    'member_id' => $member?->id,
                    'member_name' => $member ? trim($member->first_name . ' ' . $member->last_name) : 'Anonymous',
                    'total' => (float) $donation->total_amount,
                ];
            })
            ->values()
            ->all();
    }

    protected function recentDonations(array $filters): array
    {
        return $this->applyDonationFilters(Donation::query()->with(['member', 'items.fund']), $filters)
            ->latest('received_at')
            ->limit(5)
            ->get()
            ->map(function (Donation $donation) {
                $fundNames = $donation->items
                    ->map(fn (DonationItem $item) => $item->fund?->name ?? 'Unassigned')
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'id' => $donation->id,
                    'amount' => (float) $donation->amount,
                    'status' => $donation->status,
                    'received_at' => optional($donation->received_at)->toIso8601String(),
                    'member_name' => optional($donation->member)->first_name
                        ? trim($donation->member->first_name . ' ' . $donation->member->last_name)
                        : 'Anonymous',
                    'funds' => $fundNames,
                ];
            })
            ->values()
            ->all();
    }
}
