<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'tenant.resolve'])
            ->get('/tenant/ping', static function (TenantManager $manager) {
                return response()->json([
                    'tenant' => optional($manager->getTenant())->slug,
                ]);
            });
    }

    public function test_tenant_is_resolved_from_subdomain(): void
    {
        config(['tenancy.central_domains' => ['example.test']]);

        $tenant = Tenant::factory()->create(['slug' => 'grace']);

        $response = $this->getJson('http://grace.example.test/tenant/ping');

        $response->assertOk()->assertJson(['tenant' => 'grace']);
    }

    public function test_tenant_is_resolved_from_custom_domain(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'harvest']);
        TenantDomain::factory()->for($tenant)->create(['hostname' => 'harvest.church']);

        $response = $this->getJson('http://harvest.church/tenant/ping');

        $response->assertOk()->assertJson(['tenant' => 'harvest']);
    }

    public function test_reserved_subdomain_returns_not_found(): void
    {
        config(['tenancy.central_domains' => ['example.test']]);
        Tenant::factory()->create(['slug' => 'grace']);

        $response = $this->getJson('http://app.example.test/tenant/ping');

        $response->assertNotFound();
    }

    public function test_tenant_resolves_from_request_header(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'hope', 'uuid' => '11111111-1111-1111-1111-111111111111']);

        $response = $this->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/tenant/ping');

        $response->assertOk()->assertJson(['tenant' => 'hope']);
    }
}
