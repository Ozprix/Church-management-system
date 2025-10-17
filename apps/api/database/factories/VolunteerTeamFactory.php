<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\VolunteerTeam;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VolunteerTeam>
 */
class VolunteerTeamFactory extends Factory
{
    protected $model = VolunteerTeam::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $this->faker->numberBetween(1, 999),
            'description' => $this->faker->optional()->sentence(),
            'metadata' => null,
        ];
    }
}
