<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantSecurityPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantSecurityPolicy>
 */
class TenantSecurityPolicyFactory extends Factory
{
    protected $model = TenantSecurityPolicy::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'enforce_two_factor' => false,
            'enforced_role_slugs' => [],
            'enforced_permission_slugs' => [],
        ];
    }
}
