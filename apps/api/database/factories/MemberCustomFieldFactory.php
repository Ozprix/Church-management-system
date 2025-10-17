<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MemberCustomField;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MemberCustomField>
 */
class MemberCustomFieldFactory extends Factory
{
    protected $model = MemberCustomField::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'data_type' => 'text',
            'is_required' => false,
            'is_active' => true,
            'config' => null,
        ];
    }

    public function file(array $config = []): self
    {
        return $this->state(fn () => [
            'data_type' => 'file',
            'config' => $config ?: [
                'allowed_extensions' => ['pdf'],
                'allowed_mimetypes' => ['application/pdf'],
                'max_size' => 2048,
            ],
        ]);
    }

    public function signature(array $config = []): self
    {
        return $this->state(fn () => [
            'data_type' => 'signature',
            'config' => $config,
        ]);
    }
}
