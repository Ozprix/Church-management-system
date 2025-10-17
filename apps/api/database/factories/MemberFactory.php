<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Member;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        $first = $this->faker->firstName();
        $last = $this->faker->lastName();

        return [
            'tenant_id' => Tenant::factory(),
            'first_name' => $first,
            'last_name' => $last,
            'preferred_name' => $first,
            'gender' => $this->faker->randomElement(['male', 'female', 'non_binary', 'unspecified']),
            'dob' => $this->faker->optional()->dateTimeBetween('-70 years', '-5 years'),
            'marital_status' => $this->faker->optional()->randomElement(['single', 'married', 'divorced', 'widowed', 'separated', 'partnered']),
            'membership_status' => $this->faker->randomElement(['prospect', 'active', 'inactive', 'visitor']),
            'membership_stage' => $this->faker->optional()->randomElement(['welcome', 'discipleship', 'serving']),
            'joined_at' => $this->faker->optional()->dateTimeBetween('-5 years'),
            'notes' => $this->faker->optional()->paragraph(),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
