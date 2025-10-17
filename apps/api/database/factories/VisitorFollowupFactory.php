<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Member;
use App\Models\Tenant;
use App\Models\VisitorFollowup;
use App\Models\VisitorWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitorFollowup>
 */
class VisitorFollowupFactory extends Factory
{
    protected $model = VisitorFollowup::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'member_id' => Member::factory(),
            'workflow_id' => VisitorWorkflow::factory(),
            'status' => 'pending',
            'metadata' => null,
        ];
    }
}
