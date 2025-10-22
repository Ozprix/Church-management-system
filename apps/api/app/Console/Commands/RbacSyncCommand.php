<?php

namespace App\Console\Commands;

use App\Console\Concerns\ResolvesTenants;
use App\Models\Tenant;
use App\Services\Rbac\RbacManager;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RbacSyncCommand extends Command
{
    use ResolvesTenants;

    protected $signature = 'rbac:sync
        {--tenant=* : Limit synchronisation to specific tenant identifiers (ID, UUID, or slug)}
        {--prune-roles : Remove tenant roles not present in the configuration}
        {--prune-features : Remove tenant features not present in the configuration}
        {--prune-permissions : Remove global permissions not present in the configuration}';

    protected $description = 'Synchronise the RBAC registry and ensure tenant roles and features match configuration.';

    public function handle(RbacManager $manager): int
    {
        $this->info('Synchronising permission registry...');
        $manager->syncRegistry((bool) $this->option('prune-permissions'));

        $tenants = $this->resolveTenants();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found to synchronise.');

            return Command::FAILURE;
        }

        $pruneRoles = (bool) $this->option('prune-roles');
        $pruneFeatures = (bool) $this->option('prune-features');

        $processed = 0;

        foreach ($tenants as $tenant) {
            ++$processed;
            $label = $tenant->slug ?: ($tenant->uuid ?: "tenant-{$tenant->id}");
            $this->line(sprintf('[%d] Syncing tenant %s (ID: %d)...', $processed, $label, $tenant->id));

            $manager->syncTenant($tenant, $pruneRoles, $pruneFeatures);

            $this->info(' └─ Completed.');
        }

        $this->info(sprintf('Synchronised %d tenant(s).', $processed));

        return Command::SUCCESS;
    }

    protected function resolveTenants(): Collection
    {
        $identifiers = array_filter(
            (array) $this->option('tenant'),
            static fn ($value) => $value !== null && $value !== ''
        );

        if (empty($identifiers)) {
            return Tenant::query()->orderBy('id')->get();
        }

        $tenants = collect();

        foreach ($identifiers as $identifier) {
            $tenant = $this->resolveTenant($identifier);

            if (! $tenant) {
                $this->warn("Tenant [{$identifier}] was not found and will be skipped.");

                continue;
            }

            $tenants->push($tenant);
        }

        return $tenants->unique('id')->values();
    }
}
