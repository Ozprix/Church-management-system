<?php

namespace App\Console\Commands;

use App\Console\Concerns\BuildsArtisanParameters;
use App\Console\Concerns\ResolvesTenants;
use App\Console\Concerns\RunsInTenantContext;
use App\Models\Tenant;
use App\Support\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;

class TenantRunCommand extends Command
{
    use ResolvesTenants;
    use BuildsArtisanParameters;
    use RunsInTenantContext;

    protected $signature = 'tenant:run
        {tenant : Tenant ID, UUID, or slug}
        {artisan_command* : Artisan command name followed by arguments and options}';

    protected $description = 'Execute an Artisan command within the context of a specific tenant.';

    public function handle(): int
    {
        $tenantIdentifier = $this->argument('tenant');
        $tenant = $this->resolveTenant($tenantIdentifier);

        if (! $tenant) {
            $this->error("Tenant [{$tenantIdentifier}] not found.");

            return self::FAILURE;
        }

        $commandParts = $this->argument('artisan_command');

        if (empty($commandParts)) {
            $this->error('You must provide a command to execute.');

            return self::FAILURE;
        }

        $commandName = array_shift($commandParts);

        try {
            $parameters = $this->buildArtisanParameters($commandName, $commandParts);
        } catch (CommandNotFoundException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return $this->runInTenantContext(
            $tenant,
            function (TenantManager $manager) use ($commandName, $parameters): int {
                return $this->call($commandName, $parameters);
            }
        );
    }
}
