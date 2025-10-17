<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Member;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamilyMember>
 */
class FamilyMemberFactory extends Factory
{
    protected $model = FamilyMember::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'family_id' => Family::factory()->for($tenant),
            'member_id' => Member::factory()->for($tenant),
            'relationship' => $this->faker->randomElement(['head', 'spouse', 'child', 'guardian', 'relative']),
            'is_primary_contact' => false,
            'is_emergency_contact' => false,
        ];
    }

    public function primary(): self
    {
        return $this->state(fn () => ['is_primary_contact' => true]);
    }

    public function forFamilyAndMember(Family $family, Member $member): self
    {
        return $this->state(fn () => [
            'tenant_id' => $family->tenant_id,
            'family_id' => $family->id,
            'member_id' => $member->id,
        ]);
    }
}
