<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\VolunteerRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VolunteerRole>
 */
class VolunteerRoleFactory extends Factory
{
    protected $model = VolunteerRole::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->jobTitle();

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 999),
            'description' => $this->faker->optional()->sentence(),
            'skills_required' => $this->faker->randomElements(['music', 'tech', 'hospitality', 'teaching'], 2),
        ];
    }
}
