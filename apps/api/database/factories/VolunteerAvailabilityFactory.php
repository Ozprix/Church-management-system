<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Member;
use App\Models\Tenant;
use App\Models\VolunteerAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolunteerAvailability>
 */
class VolunteerAvailabilityFactory extends Factory
{
    protected $model = VolunteerAvailability::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'member_id' => Member::factory(),
            'weekdays' => ['monday', 'wednesday', 'sunday'],
            'time_blocks' => [
                ['start' => '08:00', 'end' => '12:00'],
                ['start' => '17:00', 'end' => '20:00'],
            ],
            'unavailable_from' => null,
            'unavailable_until' => null,
            'notes' => 'Prefers morning services.',
        ];
    }
}
