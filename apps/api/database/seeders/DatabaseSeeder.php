<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Rbac\RbacManager;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /** @var RbacManager $rbacManager */
        $rbacManager = app(RbacManager::class);
        $rbacManager->syncRegistry();

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'example'],
            [
                'name' => 'Example Church',
                'status' => 'active',
                'plan' => 'standard',
                'timezone' => 'UTC',
                'locale' => 'en',
            ]
        );

        $tenant->domains()->firstOrCreate(
            ['hostname' => 'example.localhost'],
            ['is_primary' => true]
        );

        $rbacManager->bootstrapTenant($tenant);

        $demoToken = config('app.demo_api_token');

        $user = User::query()->firstOrCreate(['email' => 'admin@example.com']);

        $user->forceFill([
            'tenant_id' => $tenant->id,
            'name' => 'Example Admin',
            'password' => bcrypt('password'),
        ])->save();

        $rbacManager->assignRole($user, 'admin');
        $rbacManager->grantPermissions($user, [config('permissions.super_permission')]);

        if ($demoToken) {
            $user->tokens()->where('name', 'demo-token')->delete();

            $user->tokens()->create([
                'name' => 'demo-token',
                'token' => hash('sha256', $demoToken),
                'abilities' => ['*'],
            ]);
        }

        $this->call(FinanceSeeder::class);
        $this->call(VolunteerSeeder::class);
    }
}
