<?php

namespace App\Console\Commands;

use App\Console\Concerns\BuildsArtisanParameters;
use App\Console\Concerns\ResolvesTenants;
use App\Console\Concerns\RunsInTenantContext;
use App\Models\Tenant;
use App\Support\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\Console\Exception\CommandNotFoundException;

class TenantRunBatchCommand extends Command
{
    use BuildsArtisanParameters;
    use ResolvesTenants;
    use RunsInTenantContext;

    protected $signature = 'tenant:run-batch
        {artisan_command* : Artisan command name followed by arguments and options}
        {--tenant=* : Limit execution to specific tenant identifiers}
        {--except=* : Skip specific tenant identifiers}
        {--plan=* : Filter tenants by plan}
        {--status=* : Filter tenants by status}
        {--only-active : Limit execution to tenants with active status}
        {--chunk=50 : Process tenants in chunks of this size}
        {--delay=0 : Delay in milliseconds between tenant executions}
        {--stop-on-failure : Halt execution when a tenant command fails}
        {--pretend : Output the intended actions without executing the command}';

    protected $description = 'Execute an Artisan command for multiple tenants with filtering and batching support.';

    public function handle(): int
    {
        $rawCommandParts = $this->argument('artisan_command');

        if (empty($rawCommandParts)) {
            $this->error('You must provide a command to execute.');

            return self::FAILURE;
        }

        $commandParts = $rawCommandParts;
        $commandName = array_shift($commandParts);

        try {
            $parameters = $this->buildArtisanParameters($commandName, $commandParts);
        } catch (CommandNotFoundException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $query = Tenant::query()->orderBy('id');

        $includedIds = $this->resolveTenantIdentifiers((array) $this->option('tenant'), 'tenant');
        if (! empty($includedIds)) {
            $query->whereIn('id', $includedIds);
        } elseif ($this->hasOptionInput('tenant') && empty($includedIds)) {
            $this->error('No tenants matched the provided --tenant filters.');

            return self::FAILURE;
        }

        $excludedIds = $this->resolveTenantIdentifiers((array) $this->option('except'), 'except');
        if (! empty($excludedIds)) {
            $query->whereNotIn('id', $excludedIds);
        }

        $plans = $this->normalizeOptionValues((array) $this->option('plan'));
        if (! empty($plans)) {
            $query->whereIn('plan', $plans);
        }

        $statuses = $this->normalizeOptionValues((array) $this->option('status'));
        if ($this->option('only-active')) {
            $query->where('status', 'active');
        } elseif (! empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        $tenantCount = (clone $query)->count();

        if ($tenantCount === 0) {
            $this->warn('No tenants matched the provided filters.');

            return self::SUCCESS;
        }

        $chunkSize = max(1, (int) ($this->option('chunk') ?? 50));
        $delayMs = max(0, (int) ($this->option('delay') ?? 0));
        $stopOnFailure = (bool) $this->option('stop-on-failure');
        $pretend = (bool) $this->option('pretend');

        $this->info("Executing [{$commandName}] for {$tenantCount} tenant(s).");
        if ($pretend) {
            $this->comment('Pretend mode enabled - no commands will be executed.');
        }

        $processed = 0;
        $successCount = 0;
        $failures = [];
        $rawCommand = $commandName . ($commandParts ? ' ' . implode(' ', $commandParts) : '');
        $stopProcessing = false;

        $query->chunkById($chunkSize, function ($tenants) use (
            &$processed,
            &$successCount,
            &$failures,
            &$stopProcessing,
            $commandName,
            $parameters,
            $delayMs,
            $stopOnFailure,
            $pretend,
            $rawCommand
        ) {
            foreach ($tenants as $tenant) {
                if ($stopProcessing) {
                    return false;
                }

                ++$processed;
                $slugOrFallback = $tenant->slug ?: ($tenant->uuid ?: 'no-slug');
                $label = "{$tenant->id} ({$slugOrFallback})";
                $this->line("[{$processed}] Tenant {$label}");

                if ($pretend) {
                    $this->comment(" └─ Would run: {$rawCommand}");
                    ++$successCount;

                    continue;
                }

                $exitCode = $this->runInTenantContext(
                    $tenant,
                    function (TenantManager $manager) use ($commandName, $parameters): int {
                        return $this->call($commandName, $parameters);
                    }
                );

                if ($exitCode !== Command::SUCCESS) {
                    $failures[] = [
                        'tenant' => $tenant,
                        'exitCode' => $exitCode,
                    ];

                    $this->error(" └─ Command failed with exit code {$exitCode}.");

                    if ($stopOnFailure) {
                        $stopProcessing = true;

                        return false;
                    }

                    continue;
                }

                $this->info(' └─ Command completed successfully.');
                ++$successCount;

                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }

            return true;
        });

        $failedCount = count($failures);
        $summaryMessage = sprintf(
            'Summary: processed %d, succeeded %d, failed %d%s.',
            $processed,
            $successCount,
            $failedCount,
            $pretend ? ' (pretend mode)' : ''
        );

        if ($failedCount > 0) {
            $this->warn($summaryMessage);
        } else {
            $this->info($summaryMessage);
        }

        if (! empty($failures)) {
            $this->error(sprintf('%d tenant(s) reported failures.', count($failures)));

            foreach ($failures as $failure) {
                /** @var Tenant $tenant */
                $tenant = $failure['tenant'];
                $exitCode = Arr::get($failure, 'exitCode');
                $this->error(sprintf(
                    ' - Tenant #%d (%s) exit code: %s',
                    $tenant->id,
                    $tenant->slug ?? $tenant->uuid ?? $tenant->name,
                    $exitCode
                ));
            }

            return self::FAILURE;
        }

        $this->info('All tenant commands completed successfully.');

        return self::SUCCESS;
    }

    /**
     * @param array<int, string|null> $values
     * @return array<int>
     */
    protected function resolveTenantIdentifiers(array $values, string $context): array
    {
        $identifiers = $this->normalizeOptionValues($values);

        if (empty($identifiers)) {
            return [];
        }

        $resolved = [];

        foreach ($identifiers as $identifier) {
            $tenant = $this->resolveTenant($identifier);

            if ($tenant) {
                $resolved[] = (int) $tenant->id;

                continue;
            }

            $this->warn("Tenant [{$identifier}] provided via --{$context} was not found and will be skipped.");
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @param array<int, string|null> $values
     * @return array<int, string>
     */
    protected function normalizeOptionValues(array $values): array
    {
        return collect($values)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->flatMap(function ($value) {
                $value = (string) $value;

                if (str_contains($value, ',')) {
                    return array_map('trim', explode(',', $value));
                }

                return [$value];
            })
            ->filter(fn ($value) => $value !== '')
            ->values()
            ->all();
    }

    protected function hasOptionInput(string $option): bool
    {
        $definition = $this->option($option);

        if (is_array($definition)) {
            return ! empty(array_filter($definition, fn ($value) => $value !== null && $value !== ''));
        }

        return $definition !== null;
    }
}
