<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Support\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TenantCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        TenantAwareCommandStub::reset();
        TenantSeedCommandStub::reset();
    }

    public function test_tenant_run_executes_command_with_tenant_context(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();

        Artisan::command('tenant:stub {name?}', function (?string $name, TenantManager $manager): void {
            TenantAwareCommandStub::$tenantId = optional($manager->getTenant())->getKey();
            TenantAwareCommandStub::$arguments[] = $name;
        });

        Artisan::call('tenant:run', [
            'tenant' => $tenant->getKey(),
            'artisan_command' => ['tenant:stub', 'Samuel'],
        ]);

        $this->assertSame($tenant->getKey(), TenantAwareCommandStub::$tenantId);
        $this->assertSame(['Samuel'], TenantAwareCommandStub::$arguments);
    }

    public function test_tenant_run_passes_through_options(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();

        Artisan::command('tenant:option-stub {--uppercase}', function (TenantManager $manager): void {
            TenantAwareCommandStub::$tenantId = optional($manager->getTenant())->getKey();
            TenantAwareCommandStub::$options['uppercase'] = (bool) $this->option('uppercase');
        });

        Artisan::call('tenant:run', [
            'tenant' => $tenant->slug,
            'artisan_command' => ['tenant:option-stub', '--uppercase'],
        ]);

        $this->assertSame($tenant->getKey(), TenantAwareCommandStub::$tenantId);
        $this->assertTrue(TenantAwareCommandStub::$options['uppercase']);
    }

    public function test_tenant_seed_runs_seeder_with_tenant_context(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();

        Artisan::call('tenant:seed', [
            'tenant' => $tenant->uuid,
            '--class' => TenantSeedCommandStub::class,
        ]);

        $this->assertSame($tenant->getKey(), TenantSeedCommandStub::$tenantId);
    }
}

class TenantAwareCommandStub
{
    public static ?int $tenantId = null;

    /** @var array<int, mixed> */
    public static array $arguments = [];

    /** @var array<string, mixed> */
    public static array $options = [];

    public static function reset(): void
    {
        self::$tenantId = null;
        self::$arguments = [];
        self::$options = [];
    }
}

class TenantSeedCommandStub extends Seeder
{
    public static ?int $tenantId = null;

    public static function reset(): void
    {
        self::$tenantId = null;
    }

    public function run(): void
    {
        /** @var TenantManager $manager */
        $manager = app(TenantManager::class);
        self::$tenantId = optional($manager->getTenant())->getKey();
    }
}
