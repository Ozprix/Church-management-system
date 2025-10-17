<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Fund;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Fund>
 */
class FundFactory extends Factory
{
    protected $model = Fund::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name) . '-' . $this->faker->numberBetween(1, 9999),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
            'metadata' => null,
        ];
    }
}
