<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Gathering;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Gathering>
 */
class GatheringFactory extends Factory
{
    protected $model = Gathering::class;

    public function definition(): array
    {
        $startsAt = $this->faker->dateTimeBetween('-2 weeks', '+4 weeks');
        $endsAt = (clone $startsAt)->modify('+2 hours');

        return [
            'tenant_id' => Tenant::factory(),
            'service_id' => null,
            'uuid' => (string) Str::uuid(),
            'name' => $this->faker->sentence(3),
            'status' => $this->faker->randomElement(['scheduled', 'completed']),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'location' => $this->faker->optional()->address(),
            'notes' => $this->faker->optional()->paragraph(),
            'metadata' => null,
        ];
    }

    public function forService(Service $service): self
    {
        return $this->state(fn () => [
            'tenant_id' => $service->tenant_id,
            'service_id' => $service->id,
        ]);
    }

    public function inProgress(): self
    {
        return $this->state(fn () => ['status' => 'in_progress']);
    }
}
