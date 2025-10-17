<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Services\FinanceService;
use App\Services\NotificationService;
use App\Services\Notifications\EmailChannelDriver;
use App\Services\Notifications\SmsChannelDriver;
use App\Support\Tenancy\TenantManager;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsChannelDriver::class);
        $this->app->singleton(EmailChannelDriver::class);

        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService(
                $app->make(SmsChannelDriver::class),
                $app->make(EmailChannelDriver::class)
            );
        });

        $this->app->singleton(FinanceService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Queue::createPayloadUsing(function (): array {
            /** @var TenantManager $manager */
            $manager = App::make(TenantManager::class);

            if (! $manager->hasTenant()) {
                return [];
            }

            return ['tenant_id' => $manager->getTenant()->getKey()];
        });

        Queue::before(function (JobProcessing $event): void {
            $tenantId = $event->job->payload()['tenant_id'] ?? null;

            if (! $tenantId) {
                return;
            }

            $tenant = Tenant::query()->find($tenantId);

            if (! $tenant) {
                return;
            }

            /** @var TenantManager $manager */
            $manager = App::make(TenantManager::class);
            $manager->setTenant($tenant);
        });

        $cleanupTenantContext = function (): void {
            /** @var TenantManager $manager */
            $manager = App::make(TenantManager::class);
            $manager->forgetTenant();
        };

        Queue::after(function (JobProcessed $event) use ($cleanupTenantContext): void {
            if (array_key_exists('tenant_id', $event->job->payload())) {
                $cleanupTenantContext();
            }
        });

        Queue::failing(function (JobFailed $event) use ($cleanupTenantContext): void {
            if (array_key_exists('tenant_id', $event->job->payload())) {
                $cleanupTenantContext();
            }
        });
    }
}
