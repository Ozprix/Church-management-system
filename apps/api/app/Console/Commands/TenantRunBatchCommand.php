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

    protected string $outputFormat = 'human';

    /** @var array<int, string> */
    protected array $skippedFilterIdentifiers = [];

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
        {--pretend : Output the intended actions without executing the command}
        {--confirm : Require confirmation before executing commands}
        {--yes : Automatically confirm prompts triggered by --confirm}
        {--format=human : Output format for the final summary (human, json)}';

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

        $format = strtolower((string) ($this->option('format') ?? 'human'));
        if (! in_array($format, ['human', 'json'], true)) {
            $this->error("Unsupported --format value [{$format}]. Allowed values: human, json.");

            return self::FAILURE;
        }

        $this->outputFormat = $format;
        $this->skippedFilterIdentifiers = [];

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
            if ($format === 'json') {
                $this->line(json_encode([
                    'processed' => 0,
                    'succeeded' => 0,
                    'failed' => 0,
                    'pretend' => (bool) $this->option('pretend'),
                    'stop_on_failure' => (bool) $this->option('stop-on-failure'),
                    'failures' => [],
                    'skipped_filters' => $this->skippedFilterIdentifiers,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->warn('No tenants matched the provided filters.');
            }

            return self::SUCCESS;
        }

        $chunkSize = max(1, (int) ($this->option('chunk') ?? 50));
        $delayMs = max(0, (int) ($this->option('delay') ?? 0));
        $stopOnFailure = (bool) $this->option('stop-on-failure');
        $pretend = (bool) $this->option('pretend');
        $requiresConfirmation = (bool) $this->option('confirm');
        $assumeYes = (bool) $this->option('yes');
        $rawCommand = $commandName . ($commandParts ? ' ' . implode(' ', $commandParts) : '');

        if ($format === 'human') {
            $this->info("Executing [{$commandName}] for {$tenantCount} tenant(s).");
            if ($pretend) {
                $this->comment('Pretend mode enabled - no commands will be executed.');
            }
        }

        if (! $pretend && $requiresConfirmation && ! $assumeYes && ! $this->isNonInteractive()) {
            if (! $this->confirm("Execute [{$rawCommand}] for {$tenantCount} tenant(s)?")) {
                if ($format === 'human') {
                    $this->warn('Execution cancelled.');
                }

                return self::FAILURE;
            }
        }

        $processed = 0;
        $successCount = 0;
        $failures = [];
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
            $rawCommand,
            $format
        ) {
            foreach ($tenants as $tenant) {
                if ($stopProcessing) {
                    return false;
                }

                ++$processed;
                $slugOrFallback = $tenant->slug ?: ($tenant->uuid ?: 'no-slug');
                $label = "{$tenant->id} ({$slugOrFallback})";
                if ($format === 'human') {
                    $this->line("[{$processed}] Tenant {$label}");
                }

                if ($pretend) {
                    if ($format === 'human') {
                        $this->comment(" └─ Would run: {$rawCommand}");
                    }
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

                    if ($format === 'human') {
                        $this->error(" └─ Command failed with exit code {$exitCode}.");
                    }

                    if ($stopOnFailure) {
                        $stopProcessing = true;

                        return false;
                    }

                    continue;
                }

                if ($format === 'human') {
                    $this->info(' └─ Command completed successfully.');
                }
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

        $failureDetails = array_map(
            function ($failure) {
                /** @var Tenant $tenant */
                $tenant = $failure['tenant'];

                return [
                    'tenant_id' => $tenant->id,
                    'tenant_slug' => $tenant->slug,
                    'tenant_uuid' => $tenant->uuid,
                    'exit_code' => Arr::get($failure, 'exitCode'),
                ];
            },
            $failures
        );

        $summaryPayload = [
            'processed' => $processed,
            'succeeded' => $successCount,
            'failed' => $failedCount,
            'pretend' => $pretend,
            'stop_on_failure' => $stopOnFailure,
            'failures' => $failureDetails,
            'skipped_filters' => $this->skippedFilterIdentifiers,
        ];

        if ($format === 'json') {
            $this->line(json_encode($summaryPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
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
            } else {
                $this->info('All tenant commands completed successfully.');
            }
        }

        if (! empty($failures)) {
            return self::FAILURE;
        }

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

            $this->skippedFilterIdentifiers[] = "{$context}:{$identifier}";

            if ($this->outputFormat === 'human') {
                $this->warn("Tenant [{$identifier}] provided via --{$context} was not found and will be skipped.");
            }
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

    protected function isNonInteractive(): bool
    {
        return (bool) $this->input->getOption('no-interaction') || (bool) $this->input->getOption('quiet');
    }
}
