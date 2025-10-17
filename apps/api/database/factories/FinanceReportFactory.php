<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FinanceReport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinanceReport>
 */
class FinanceReportFactory extends Factory
{
    protected $model = FinanceReport::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'requested_by' => User::factory(),
            'type' => $this->faker->randomElement(['donations', 'pledges', 'donor-statement']),
            'status' => 'pending',
            'disk' => 'reports',
            'file_path' => null,
            'filters' => null,
            'generated_at' => null,
            'failure_reason' => null,
        ];
    }
}
