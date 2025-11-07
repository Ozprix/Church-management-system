<?php

namespace Database\Factories;

use App\Models\AttendanceAnalyticsReport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceAnalyticsReport>
 */
class AttendanceAnalyticsReportFactory extends Factory
{
    protected $model = AttendanceAnalyticsReport::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'name' => fake()->sentence(3),
            'filters' => null,
            'frequency' => 'none',
            'channel' => 'email',
            'email_recipient' => null,
            'last_run_at' => null,
        ];
    }
}
