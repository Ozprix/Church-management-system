<?php

namespace App\Services\Billing;

use App\Models\Tenant;
use App\Models\TenantPlan;
use Illuminate\Support\Facades\Log;

class BillingService
{
    protected string $driver;

    public function __construct()
    {
        $this->driver = config('billing.driver', 'log');
    }

    public function provisionSubscription(Tenant $tenant, TenantPlan $subscription): void
    {
        if ($subscription->status !== 'active') {
            return;
        }

        if ($this->driver === 'none') {
            return;
        }

        $this->logEvent('provision_subscription', $tenant, $subscription);
    }

    public function processTrialExpiration(TenantPlan $subscription): void
    {
        if ($this->driver === 'none') {
            return;
        }

        $this->logEvent('trial_expired', $subscription->tenant, $subscription);
    }

    protected function logEvent(string $event, ?Tenant $tenant, TenantPlan $subscription): void
    {
        Log::info('billing_event', [
            'event' => $event,
            'tenant_id' => $tenant?->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status,
        ]);
    }
}
