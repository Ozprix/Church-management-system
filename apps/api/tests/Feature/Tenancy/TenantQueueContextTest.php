<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Jobs\Concerns\DispatchesForTenant;
use App\Models\Tenant;
use App\Support\Tenancy\TenantManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Tests\TestCase;

class TenantQueueContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_for_tenant_runs_job_with_context(): void
    {
        $tenant = Tenant::factory()->create();

        /** @var TenantManager $manager */
        $manager = app(TenantManager::class);
        $manager->forgetTenant();

        TenantAwareJobStub::$handledTenantId = null;

        TenantAwareJobStub::dispatchForTenant($tenant);

        $this->assertSame($tenant->getKey(), TenantAwareJobStub::$handledTenantId);

        $this->assertFalse($manager->hasTenant());
    }

    public function test_job_uses_current_tenant_context_when_available(): void
    {
        $tenant = Tenant::factory()->create();

        /** @var TenantManager $manager */
        $manager = app(TenantManager::class);
        $manager->setTenant($tenant);

        TenantAwareJobStub::$handledTenantId = null;

        TenantAwareJobStub::dispatch();

        $this->assertSame($tenant->getKey(), TenantAwareJobStub::$handledTenantId);
    }

    public function test_job_without_tenant_runs_without_context(): void
    {
        /** @var TenantManager $manager */
        $manager = app(TenantManager::class);
        $manager->forgetTenant();

        TenantAwareJobStub::$handledTenantId = null;

        TenantAwareJobStub::dispatch();

        $this->assertNull(TenantAwareJobStub::$handledTenantId);
    }
}

class TenantAwareJobStub implements ShouldQueue
{
    use DispatchesForTenant;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public static ?int $handledTenantId = null;

    public function handle(TenantManager $manager): void
    {
        self::$handledTenantId = $manager->getTenant()?->getKey();
    }
}
