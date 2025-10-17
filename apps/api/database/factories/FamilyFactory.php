<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Family;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Family>
 */
class FamilyFactory extends Factory
{
    protected $model = Family::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'family_name' => $this->faker->lastName().' Household',
            'address' => [
                'line1' => $this->faker->streetAddress(),
                'city' => $this->faker->city(),
                'state' => $this->faker->stateAbbr(),
                'postal_code' => $this->faker->postcode(),
                'country' => $this->faker->countryCode(),
            ],
            'photo_path' => null,
            'notes' => $this->faker->optional()->sentence(),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
