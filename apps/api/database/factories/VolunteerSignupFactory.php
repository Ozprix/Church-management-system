<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\VolunteerRole;
use App\Models\VolunteerSignup;
use Illuminate\Database\Eloquent\Factories\Factory;

class VolunteerSignupFactory extends Factory
{
    protected $model = VolunteerSignup::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'volunteer_role_id' => VolunteerRole::factory(),
            'volunteer_team_id' => null,
            'member_id' => null,
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'status' => 'pending',
            'applied_at' => now(),
            'notes' => null,
            'metadata' => null,
        ];
    }
}
