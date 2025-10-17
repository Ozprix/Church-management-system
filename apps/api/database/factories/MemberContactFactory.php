<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberContact;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberContact>
 */
class MemberContactFactory extends Factory
{
    protected $model = MemberContact::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['email', 'mobile', 'home_phone']);
        $value = match ($type) {
            'email' => $this->faker->unique()->safeEmail(),
            'mobile' => $this->faker->e164PhoneNumber(),
            'home_phone' => $this->faker->phoneNumber(),
            default => $this->faker->word(),
        };

        return [
            'tenant_id' => Tenant::factory(),
            'member_id' => Member::factory(),
            'type' => $type,
            'label' => ucfirst($type),
            'value' => $value,
            'is_primary' => true,
            'is_emergency' => false,
            'communication_preference' => $type === 'email' ? 'email' : 'sms',
        ];
    }

    public function forMember(Member $member): self
    {
        return $this->state(fn () => [
            'tenant_id' => $member->tenant_id,
            'member_id' => $member->id,
        ]);
    }
}
