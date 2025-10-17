<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NotificationTemplate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->sentence(3);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 999),
            'channel' => $this->faker->randomElement(['sms', 'email']),
            'subject' => $this->faker->optional()->sentence(),
            'body' => $this->faker->paragraph(),
            'placeholders' => ['name', 'event'],
            'metadata' => null,
        ];
    }

    public function sms(): self
    {
        return $this->state(fn () => ['channel' => 'sms', 'subject' => null]);
    }

    public function email(): self
    {
        return $this->state(fn () => ['channel' => 'email']);
    }
}
