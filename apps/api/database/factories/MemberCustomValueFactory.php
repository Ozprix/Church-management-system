<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberCustomField;
use App\Models\MemberCustomValue;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberCustomValue>
 */
class MemberCustomValueFactory extends Factory
{
    protected $model = MemberCustomValue::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'member_id' => Member::factory(),
            'field_id' => MemberCustomField::factory(),
            'value_string' => $this->faker->word(),
        ];
    }
}
