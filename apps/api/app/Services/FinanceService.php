<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\DonationItem;
use App\Models\FinancialLedgerEntry;
use App\Models\Fund;
use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\Pledge;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    public function createFund(array $attributes): Fund
    {
        return Fund::create(Arr::only($attributes, [
            'tenant_id',
            'name',
            'slug',
            'description',
            'is_active',
            'metadata',
        ]));
    }

    public function updateFund(Fund $fund, array $attributes): Fund
    {
        $fund->fill(Arr::only($attributes, ['name', 'slug', 'description', 'is_active', 'metadata']));
        $fund->save();

        return $fund->fresh();
    }

    private const DEFAULT_INCOME_ACCOUNT = 'Donations Income';
    private const DEFAULT_ASSET_ACCOUNT = 'Cash - Undeposited Funds';

    public function recordDonation(array $attributes): Donation
    {
        return DB::transaction(function () use ($attributes): Donation {
            $tenantId = $attributes['tenant_id'] ?? null;

            $donationAttributes = Arr::only($attributes, [
                'tenant_id',
                'member_id',
                'payment_method_id',
                'amount',
                'currency',
                'status',
                'received_at',
                'provider',
                'provider_reference',
                'receipt_number',
                'notes',
                'metadata',
            ]);

            if (! empty($donationAttributes['member_id'])) {
                Member::query()->where('tenant_id', $tenantId)->findOrFail($donationAttributes['member_id']);
            }

            if (! empty($donationAttributes['payment_method_id'])) {
                PaymentMethod::query()->where('tenant_id', $tenantId)->findOrFail($donationAttributes['payment_method_id']);
            }

            $donationAttributes['received_at'] = $donationAttributes['received_at'] ?? Carbon::now();

            /** @var Donation $donation */
            $donation = Donation::create($donationAttributes);

            $items = $attributes['items'] ?? [];
            $this->syncDonationItems($donation, $items);

            $this->syncDonationLedgerEntries($donation);

            return $donation->fresh(['items.fund', 'member']);
        });
    }

    public function updateDonation(Donation $donation, array $attributes): Donation
    {
        $previousStatus = $donation->status;
        $donation->fill(Arr::only($attributes, [
            'amount',
            'currency',
            'status',
            'received_at',
            'provider',
            'provider_reference',
            'receipt_number',
            'notes',
            'metadata',
        ]));
        $donation->save();

        if (array_key_exists('items', $attributes)) {
            $this->syncDonationItems($donation, $attributes['items']);
        }

        $donation->refresh();

        $this->syncDonationLedgerEntries($donation, $previousStatus);

        return $donation->fresh(['items.fund', 'member']);
    }

    public function createPledge(array $attributes): Pledge
    {
        $tenantId = $attributes['tenant_id'] ?? null;

        if (! empty($attributes['member_id'])) {
            Member::query()->where('tenant_id', $tenantId)->findOrFail($attributes['member_id']);
        }

        if (! empty($attributes['fund_id'])) {
            Fund::query()->where('tenant_id', $tenantId)->findOrFail($attributes['fund_id']);
        }

        return Pledge::create(Arr::only($attributes, [
            'tenant_id',
            'member_id',
            'fund_id',
            'amount',
            'fulfilled_amount',
            'currency',
            'frequency',
            'start_date',
            'end_date',
            'status',
            'notes',
            'metadata',
        ]));
    }

    public function updatePledge(Pledge $pledge, array $attributes): Pledge
    {
        $pledge->fill(Arr::only($attributes, [
            'fund_id',
            'amount',
            'fulfilled_amount',
            'currency',
            'frequency',
            'start_date',
            'end_date',
            'status',
            'notes',
            'metadata',
        ]));

        if ($pledge->isDirty('fund_id') && $pledge->fund_id) {
            Fund::query()->where('tenant_id', $pledge->tenant_id)->findOrFail($pledge->fund_id);
        }

        $pledge->save();

        return $pledge->fresh();
    }

    protected function syncDonationItems(Donation $donation, array $items): void
    {
        $donation->items()->delete();

        foreach ($items as $item) {
            if (empty($item['amount'])) {
                continue;
            }

            if (! empty($item['fund_id'])) {
                Fund::query()->where('tenant_id', $donation->tenant_id)->findOrFail($item['fund_id']);
            }

            DonationItem::create([
                'donation_id' => $donation->id,
                'fund_id' => $item['fund_id'] ?? null,
                'amount' => $item['amount'],
            ]);
        }
    }

    protected function syncDonationLedgerEntries(Donation $donation, ?string $previousStatus = null): void
    {
        $existingEntries = $donation->ledgerEntries()->exists();
        $donation->ledgerEntries()->delete();

        if ($donation->status === 'succeeded') {
            $this->createDonationLedgerPair($donation, 'donation');

            return;
        }

        if ($donation->status === 'refunded') {
            if ($previousStatus === 'succeeded' || $existingEntries) {
                $this->createDonationLedgerPair($donation, 'donation');
            }

            $this->createRefundLedgerPair($donation);
        }
    }

    protected function createDonationLedgerPair(Donation $donation, string $kind): void
    {
        $this->createLedgerEntry($donation, 'credit', self::DEFAULT_INCOME_ACCOUNT, $kind);
        $this->createLedgerEntry($donation, 'debit', self::DEFAULT_ASSET_ACCOUNT, $kind);
    }

    protected function createRefundLedgerPair(Donation $donation): void
    {
        $this->createLedgerEntry($donation, 'debit', self::DEFAULT_INCOME_ACCOUNT, 'refund');
        $this->createLedgerEntry($donation, 'credit', self::DEFAULT_ASSET_ACCOUNT, 'refund');
    }

    protected function createLedgerEntry(Donation $donation, string $entryType, string $account, string $kind): void
    {
        FinancialLedgerEntry::create([
            'tenant_id' => $donation->tenant_id,
            'donation_id' => $donation->id,
            'entry_type' => $entryType,
            'account' => $account,
            'amount' => $donation->amount,
            'currency' => $donation->currency,
            'occurred_at' => $donation->received_at ?? Carbon::now(),
            'description' => $donation->notes,
            'metadata' => ['kind' => $kind],
        ]);
    }

    public function createPaymentMethod(array $attributes): PaymentMethod
    {
        $tenantId = $attributes['tenant_id'] ?? null;

        if (! empty($attributes['member_id'])) {
            Member::query()->where('tenant_id', $tenantId)->findOrFail($attributes['member_id']);
        }

        /** @var PaymentMethod $paymentMethod */
        $paymentMethod = PaymentMethod::create(Arr::only($attributes, [
            'tenant_id',
            'member_id',
            'type',
            'brand',
            'last_four',
            'provider',
            'provider_reference',
            'expires_at',
            'is_default',
            'metadata',
        ]));

        $this->syncDefaultPaymentMethod($paymentMethod);

        return $paymentMethod->fresh(['member']);
    }

    public function updatePaymentMethod(PaymentMethod $paymentMethod, array $attributes): PaymentMethod
    {
        $paymentMethod->fill(Arr::only($attributes, [
            'type',
            'brand',
            'last_four',
            'provider',
            'provider_reference',
            'expires_at',
            'is_default',
            'metadata',
        ]));

        if (array_key_exists('member_id', $attributes)) {
            $memberId = $attributes['member_id'];

            if (! empty($memberId)) {
                Member::query()->where('tenant_id', $paymentMethod->tenant_id)->findOrFail($memberId);
            }

            $paymentMethod->member_id = $memberId;
        }

        $paymentMethod->save();

        $this->syncDefaultPaymentMethod($paymentMethod);

        return $paymentMethod->fresh(['member']);
    }

    public function deletePaymentMethod(PaymentMethod $paymentMethod): void
    {
        $tenantId = $paymentMethod->tenant_id;
        $memberId = $paymentMethod->member_id;
        $wasDefault = $paymentMethod->is_default;

        $paymentMethod->delete();

        if (! $wasDefault) {
            return;
        }

        $replacement = PaymentMethod::query()
            ->where('tenant_id', $tenantId)
            ->when($memberId, fn ($query) => $query->where('member_id', $memberId))
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->first();

        if ($replacement) {
            $replacement->forceFill(['is_default' => true])->save();
        }
    }

    protected function syncDefaultPaymentMethod(PaymentMethod $paymentMethod): void
    {
        if (! $paymentMethod->is_default) {
            return;
        }

        PaymentMethod::query()
            ->where('tenant_id', $paymentMethod->tenant_id)
            ->where('id', '!=', $paymentMethod->id)
            ->when($paymentMethod->member_id, function ($query, $memberId) {
                $query->where('member_id', $memberId);
            }, function ($query) {
                $query->whereNull('member_id');
            })
            ->update(['is_default' => false]);
    }

    public function getDashboardSummary(int $tenantId): array
    {
        $donationsQuery = Donation::query()->forTenant($tenantId)->where('status', 'succeeded');
        $totalDonations = (float) (clone $donationsQuery)->sum('amount');

        $currentMonthStart = Carbon::now()->startOfMonth();

        $monthDonations = (float) (clone $donationsQuery)
            ->whereDate('received_at', '>=', $currentMonthStart)
            ->sum('amount');

        $donationCount = (int) (clone $donationsQuery)->count();
        $averageDonation = $donationCount > 0 ? $totalDonations / $donationCount : 0.0;

        $activePledges = Pledge::query()
            ->forTenant($tenantId)
            ->where('status', 'active')
            ->sum('amount');

        $fulfilledPledges = Pledge::query()
            ->forTenant($tenantId)
            ->where('status', 'fulfilled')
            ->sum('amount');

        $recurringPledges = Pledge::query()
            ->forTenant($tenantId)
            ->whereIn('frequency', ['weekly', 'monthly', 'quarterly', 'annually'])
            ->where('status', 'active')
            ->get(['id', 'amount', 'frequency', 'fulfilled_amount']);

        $topFunds = DonationItem::query()
            ->selectRaw('fund_id, SUM(amount) as total_amount')
            ->whereHas('donation', fn ($query) => $query->forTenant($tenantId)->where('status', 'succeeded'))
            ->whereNotNull('fund_id')
            ->groupBy('fund_id')
            ->orderByDesc('total_amount')
            ->with('fund:id,name')
            ->limit(5)
            ->get()
            ->map(fn (DonationItem $item) => [
                'fund_id' => $item->fund_id,
                'fund_name' => $item->fund?->name,
                'total_amount' => (float) $item->total_amount,
            ])
            ->all();

        $recentDonations = Donation::query()
            ->forTenant($tenantId)
            ->with(['member', 'items.fund'])
            ->orderByDesc('received_at')
            ->limit(5)
            ->get();

        return [
            'totals' => [
                'donations' => $totalDonations,
                'month_to_date' => $monthDonations,
                'average_donation' => round($averageDonation, 2),
                'active_pledges' => (float) $activePledges,
                'fulfilled_pledges' => (float) $fulfilledPledges,
            ],
            'recurring_pledges' => $recurringPledges->map(fn (Pledge $pledge) => [
                'id' => $pledge->id,
                'amount' => (float) $pledge->amount,
                'fulfilled_amount' => (float) $pledge->fulfilled_amount,
                'frequency' => $pledge->frequency,
            ])->all(),
            'top_funds' => $topFunds,
            'recent_donations' => $recentDonations,
        ];
    }
}
