<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantPlanUsage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PlanEnforcementService
{
    public function __construct(private readonly TenantOnboardingService $tenantOnboardingService)
    {
    }

    /**
     * @throws HttpException
     */
    public function ensureCanUse(Tenant $tenant, string $feature, int $increment = 1): void
    {
        if ($this->tenantOnboardingService->canUseFeature($tenant, $feature, $increment)) {
            return;
        }

        $label = $this->featureLabel($feature);

        throw new HttpException(
            Response::HTTP_PAYMENT_REQUIRED,
            __("Plan limit reached for :feature. Upgrade your subscription to continue.", ['feature' => $label])
        );
    }

    public function recordUsage(Tenant $tenant, string $feature, int $increment = 1): void
    {
        $this->tenantOnboardingService->incrementUsage($tenant, $feature, $increment);
    }

    public function releaseUsage(Tenant $tenant, string $feature, int $decrement = 1): void
    {
        /** @var TenantPlanUsage|null $usage */
        $usage = TenantPlanUsage::query()
            ->where('tenant_id', $tenant->id)
            ->where('feature', $feature)
            ->first();

        if (! $usage) {
            return;
        }

        $usage->forceFill([
            'used' => max(0, (int) $usage->used - $decrement),
        ])->save();
    }

    protected function featureLabel(string $feature): string
    {
        return match ($feature) {
            'members' => __('members'),
            'volunteer_signups' => __('volunteer signups'),
            'domains' => __('domains'),
            'notification_rules' => __('notification rules'),
            default => Str::headline(str_replace('_', ' ', $feature)),
        };
    }
}
