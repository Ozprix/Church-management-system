<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantFeature;
use App\Models\TenantPlan;
use App\Models\TenantPlanUsage;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Rbac\RbacManager;
use App\Models\VolunteerSignup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantOnboardingService
{
    public function __construct(
        private readonly RbacManager $rbacManager,
        private readonly ?BillingService $billingService = null
    ) {
    }

    public function createTenant(array $attributes): Tenant
    {
        return DB::transaction(function () use ($attributes): Tenant {
            $tenantAttributes = Arr::only($attributes, [
                'name',
                'slug',
                'status',
                'plan',
                'timezone',
                'locale',
                'meta',
            ]);

            if (empty($tenantAttributes['status'])) {
                $tenantAttributes['status'] = 'pending';
            }

            $tenant = Tenant::create($tenantAttributes);

            $domains = Arr::get($attributes, 'domains', []);

            foreach ($domains as $domainData) {
                $tenant->domains()->create([
                    'hostname' => Str::lower($domainData['hostname']),
                    'is_primary' => (bool) Arr::get($domainData, 'is_primary', false),
                    'verification_token' => Str::uuid()->toString(),
                ]);
            }

            $this->rbacManager->bootstrapTenant($tenant);

            if ($admin = Arr::get($attributes, 'admin')) {
                $this->createInitialAdmin($tenant, $admin);
            }

            if ($planCode = Arr::get($attributes, 'plan_code')) {
                $plan = Plan::query()->where('code', $planCode)->first();
                if ($plan) {
                    $this->assignPlan($tenant, $plan, Arr::get($attributes, 'plan_options', []));
                }
            }

            return $tenant->fresh('domains');
        });
    }

    public function verifyDomain(TenantDomain $domain, string $token): bool
    {
        if (! hash_equals((string) $domain->verification_token, $token)) {
            return false;
        }

        $domain->forceFill([
            'verification_token' => null,
            'verified_at' => now(),
        ])->save();

        return true;
    }

    public function assignPlan(Tenant $tenant, Plan $plan, array $options = []): TenantPlan
    {
        return DB::transaction(function () use ($tenant, $plan, $options): TenantPlan {
            $subscription = TenantPlan::query()
                ->where('tenant_id', $tenant->id)
                ->where('plan_id', $plan->id)
                ->first();

            $payload = [
                'status' => Arr::get($options, 'status', 'active'),
                'trial_ends_at' => Arr::get($options, 'trial_ends_at'),
                'renews_at' => Arr::get($options, 'renews_at'),
                'seat_limit' => Arr::get($options, 'seat_limit'),
                'metadata' => Arr::get($options, 'metadata'),
            ];

            if ($subscription) {
                $subscription->fill($payload);
                $subscription->save();
            } else {
                $subscription = TenantPlan::create($payload + [
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                ]);
            }

            $this->seedPlanUsage($tenant, $plan);
            $this->syncPlanFeatures($tenant, $plan);

            $subscription = $subscription->fresh();
            $subscription->loadMissing('tenant');

            if ($this->billingService) {
                $this->billingService->provisionSubscription($subscription->tenant ?? $tenant, $subscription);
            }

            return $subscription;
        });
    }

    protected function seedPlanUsage(Tenant $tenant, Plan $plan): void
    {
        /** @var Collection<int, PlanFeature> $features */
        $features = $plan->features()->get();

        foreach ($features as $feature) {
            TenantPlanUsage::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'feature' => $feature->feature,
                ],
                [
                    'limit' => $feature->limit,
                    'metadata' => $feature->metadata,
                    'used' => $this->currentUsageForFeature($tenant, $feature->feature),
                ]
            );
        }
    }

    public function incrementUsage(Tenant $tenant, string $feature, int $amount = 1): TenantPlanUsage
    {
        /** @var TenantPlanUsage $usage */
        $usage = TenantPlanUsage::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'feature' => $feature],
            ['used' => 0]
        );

        $usage->increment('used', $amount);

        return $usage->fresh();
    }

    public function canUseFeature(Tenant $tenant, string $feature, int $increment = 1): bool
    {
        /** @var TenantPlanUsage|null $usage */
        $usage = TenantPlanUsage::query()->where('tenant_id', $tenant->id)->where('feature', $feature)->first();

        if (! $usage) {
            return true;
        }

        if ($usage->limit === null) {
            return true;
        }

        return ($usage->used + $increment) <= $usage->limit;
    }

    public function featureLimit(Tenant $tenant, string $feature): ?int
    {
        /** @var TenantPlanUsage|null $usage */
        $usage = TenantPlanUsage::query()
            ->where('tenant_id', $tenant->id)
            ->where('feature', $feature)
            ->first();

        return $usage?->limit;
    }

    public function profile(Tenant $tenant): array
    {
        $subscription = $tenant->planSubscriptions()->latest()->with('plan.features')->first();

        return [
            'tenant' => $tenant->toArray(),
            'subscription' => $subscription?->toArray(),
            'plan' => $subscription?->plan?->only(['id', 'code', 'name', 'description']),
            'features' => $tenant->features()->get(['feature', 'is_enabled', 'metadata'])->toArray(),
            'usage' => $tenant->planUsages()->get(['feature', 'used', 'limit', 'metadata'])->toArray(),
            'domains' => $tenant->domains()->get()->toArray(),
        ];
    }

    public function addDomain(Tenant $tenant, array $attributes): TenantDomain
    {
        return DB::transaction(function () use ($tenant, $attributes): TenantDomain {
            $isPrimary = (bool) Arr::get($attributes, 'is_primary', false);

            if ($isPrimary) {
                $tenant->domains()->update(['is_primary' => false]);
            }

            return $tenant->domains()->create([
                'hostname' => Str::lower(Arr::get($attributes, 'hostname')),
                'is_primary' => $isPrimary,
                'verification_token' => Str::uuid()->toString(),
                'verified_at' => null,
            ]);
        });
    }

    public function regenerateDomainToken(TenantDomain $domain): TenantDomain
    {
        $domain->forceFill([
            'verification_token' => Str::uuid()->toString(),
            'verified_at' => null,
        ])->save();

        return $domain->fresh();
    }

    public function deleteDomain(TenantDomain $domain): void
    {
        if ($domain->is_primary) {
            throw new \InvalidArgumentException('Cannot delete primary domain.');
        }

        $domain->delete();
    }

    protected function createInitialAdmin(Tenant $tenant, array $admin): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => Arr::get($admin, 'name'),
            'email' => Arr::get($admin, 'email'),
            'password' => Hash::make(Arr::get($admin, 'password', Str::random(16))),
            'email_verified_at' => now(),
        ]);

        $this->rbacManager->assignRole($user, 'tenant_owner');

        return $user;
    }

    protected function syncPlanFeatures(Tenant $tenant, Plan $plan): void
    {
        $features = $plan->features()->get()->pluck('feature')->all();

        foreach ($features as $feature) {
            TenantFeature::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'feature' => $feature],
                ['is_enabled' => true]
            );
        }
    }

    protected function currentUsageForFeature(Tenant $tenant, string $feature): int
    {
        return match ($feature) {
            'members' => $tenant->members()->count(),
            'volunteer_signups' => VolunteerSignup::query()->where('tenant_id', $tenant->id)->count(),
            'domains' => $tenant->domains()->count(),
            default => (int) (TenantPlanUsage::query()
                ->where('tenant_id', $tenant->id)
                ->where('feature', $feature)
                ->value('used') ?? 0),
        };
    }
}
