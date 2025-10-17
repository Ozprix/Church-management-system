<?php

namespace Database\Factories;

use App\Models\MembershipProcess;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class MembershipProcessFactory extends Factory
{
    protected $model = MembershipProcess::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->words(3, true),
            'slug' => $this->faker->unique()->slug(),
            'description' => $this->faker->sentence(),
            'is_active' => true,
            'auto_start_on_member_create' => false,
            'metadata' => null,
        ];
    }
}
