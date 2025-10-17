<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Gathering;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\VolunteerAssignment;
use App\Models\VolunteerRole;
use App\Models\VolunteerTeam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolunteerAssignment>
 */
class VolunteerAssignmentFactory extends Factory
{
    protected $model = VolunteerAssignment::class;

    public function definition(): array
    {
        $startsAt = $this->faker->dateTimeBetween('+1 day', '+4 weeks');

        return [
            'tenant_id' => Tenant::factory(),
            'member_id' => Member::factory(),
            'volunteer_role_id' => VolunteerRole::factory(),
            'volunteer_team_id' => VolunteerTeam::factory(),
            'gathering_id' => Gathering::factory(),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'status' => $this->faker->randomElement(['scheduled', 'confirmed']),
            'notes' => null,
        ];
    }
}
