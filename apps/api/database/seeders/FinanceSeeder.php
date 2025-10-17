<?php

namespace Database\Seeders;

use App\Models\Fund;
use App\Models\Member;
use App\Models\Tenant;
use App\Services\FinanceService;
use App\Services\Rbac\RbacManager;
use App\Support\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create([
            'name' => 'Demo Fellowship',
            'slug' => 'demo-fellowship',
        ]);

        /** @var TenantManager $tenantManager */
        $tenantManager = app(TenantManager::class);
        $tenantManager->setTenant($tenant);

        /** @var FinanceService $financeService */
        $financeService = app(FinanceService::class);

        /** @var RbacManager $rbacManager */
        $rbacManager = app(RbacManager::class);
        $rbacManager->bootstrapTenant($tenant);

        $funds = Fund::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $members = Member::factory()->count(5)->create(['tenant_id' => $tenant->id]);

        foreach ($members as $index => $member) {
            $financeService->createPaymentMethod([
                'tenant_id' => $tenant->id,
                'member_id' => $member->id,
                'type' => $index % 2 === 0 ? 'card' : 'mobile_money',
                'brand' => $index % 2 === 0 ? 'Visa' : 'MTN',
                'last_four' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                'provider' => $index % 2 === 0 ? 'stripe' : 'hubtel',
                'is_default' => $index === 0,
                'metadata' => ['seeded' => true],
            ]);

            $financeService->createPledge([
                'tenant_id' => $tenant->id,
                'member_id' => $member->id,
                'fund_id' => Arr::random($funds)->id,
                'amount' => random_int(100, 500),
                'fulfilled_amount' => random_int(0, 300),
                'currency' => 'USD',
                'frequency' => Arr::random(['weekly', 'monthly', 'quarterly']),
                'status' => Arr::random(['active', 'fulfilled']),
            ]);
        }

        foreach (range(1, 6) as $value) {
            $member = Arr::random($members);
            $fund = Arr::random($funds);
            $status = $value % 5 === 0 ? 'refunded' : 'succeeded';

            $donation = $financeService->recordDonation([
                'tenant_id' => $tenant->id,
                'member_id' => $member->id,
                'amount' => random_int(50, 400),
                'currency' => 'USD',
                'status' => $status === 'refunded' ? 'succeeded' : $status,
                'received_at' => Carbon::now()->subDays(random_int(0, 20))->toIso8601String(),
                'items' => [
                    ['fund_id' => $fund->id, 'amount' => random_int(50, 400)],
                ],
            ]);

            if ($status === 'refunded') {
                $financeService->updateDonation($donation, ['status' => 'refunded']);
            }
        }

        $tenantManager->forgetTenant();
    }
}
