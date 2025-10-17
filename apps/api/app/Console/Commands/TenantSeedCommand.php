<?php

namespace App\Console\Commands;

use App\Console\Concerns\ResolvesTenants;
use App\Console\Concerns\RunsInTenantContext;
use App\Models\Tenant;
use App\Support\Tenancy\TenantManager;
use Illuminate\Console\Command;

class TenantSeedCommand extends Command
{
    use ResolvesTenants;
    use RunsInTenantContext;

    protected $signature = 'tenant:seed
        {tenant : Tenant ID, UUID, or slug}
        {--class=DatabaseSeeder : The seeder class to run}
        {--database= : The database connection to seed}
        {--force : Force the operation to run when in production}';

    protected $description = 'Seed the database for a specific tenant context.';

    public function handle(): int
    {
        $tenantIdentifier = $this->argument('tenant');
        $tenant = $this->resolveTenant($tenantIdentifier);

        if (! $tenant) {
            $this->error("Tenant [{$tenantIdentifier}] not found.");

            return self::FAILURE;
        }

        $parameters = [
            '--class' => $this->option('class') ?? 'DatabaseSeeder',
        ];

        if ($database = $this->option('database')) {
            $parameters['--database'] = $database;
        }

        if ($this->option('force')) {
            $parameters['--force'] = true;
        }

        return $this->runInTenantContext(
            $tenant,
            function (TenantManager $manager) use ($parameters): int {
                return $this->call('db:seed', $parameters);
            }
        );
    }
}
