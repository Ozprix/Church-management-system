<?php

namespace Database\Factories;

use App\Models\MemberImport;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemberImportFactory extends Factory
{
    protected $model = MemberImport::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => null,
            'original_filename' => $this->faker->lexify('import-????.csv'),
            'stored_path' => 'member-imports/' . $this->faker->uuid . '/import.csv',
            'status' => MemberImport::STATUS_PENDING,
            'total_rows' => $this->faker->numberBetween(0, 50),
            'processed_rows' => 0,
            'failed_rows' => 0,
            'errors' => null,
        ];
    }
}
