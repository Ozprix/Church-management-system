<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 999),
            'short_code' => strtoupper(Str::random(4)),
            'description' => $this->faker->optional()->sentence(12),
            'default_location' => $this->faker->optional()->city(),
            'default_start_time' => $this->faker->optional()->time('H:i:00'),
            'default_duration_minutes' => $this->faker->optional()->numberBetween(45, 180),
            'absence_threshold' => $this->faker->numberBetween(2, 5),
            'metadata' => null,
        ];
    }

    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn () => ['tenant_id' => $tenant->id]);
    }
}
