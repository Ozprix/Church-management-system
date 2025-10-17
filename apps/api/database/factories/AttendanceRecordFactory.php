<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\Gathering;
use App\Models\Member;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['present', 'absent', 'excused']);
        $checkedIn = $status === 'present' ? $this->faker->dateTimeBetween('-1 hour', '+1 hour') : null;
        $checkedOut = $checkedIn ? (clone $checkedIn)->modify('+' . $this->faker->numberBetween(60, 150) . ' minutes') : null;

        return [
            'tenant_id' => Tenant::factory(),
            'gathering_id' => null,
            'member_id' => null,
            'status' => $status,
            'check_in_method' => $status === 'present' ? $this->faker->randomElement(['qr', 'manual', 'kiosk']) : null,
            'checked_in_at' => $checkedIn,
            'checked_out_at' => $checkedOut,
            'notes' => $status !== 'present' ? ['reason' => $this->faker->sentence()] : null,
        ];
    }

    public function forGathering(Gathering $gathering): self
    {
        return $this->state(fn () => [
            'tenant_id' => $gathering->tenant_id,
            'gathering_id' => $gathering->id,
        ]);
    }

    public function forMember(Member $member): self
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $member->tenant_id,
            'member_id' => $member->id,
        ] + $attributes);
    }
}
