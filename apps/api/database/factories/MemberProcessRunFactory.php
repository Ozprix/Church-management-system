<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberProcessRun;
use App\Models\MembershipProcess;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemberProcessRunFactory extends Factory
{
    protected $model = MemberProcessRun::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'member_id' => Member::factory(),
            'process_id' => MembershipProcess::factory(),
            'current_stage_id' => null,
            'status' => 'pending',
            'started_at' => now(),
            'metadata' => null,
        ];
    }
}
