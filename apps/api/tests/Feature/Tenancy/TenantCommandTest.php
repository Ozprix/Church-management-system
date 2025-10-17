<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Support\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TenantCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        TenantAwareCommandStub::reset();
        TenantSeedCommandStub::reset();
        TenantBatchCommandStub::reset();
    }

    public function test_tenant_run_executes_command_with_tenant_context(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();

        Artisan::command('tenant:stub {name?}', function (?string $name, TenantManager $manager): void {
            TenantAwareCommandStub::$tenantId = optional($manager->getTenant())->getKey();
            TenantAwareCommandStub::$arguments[] = $name;
        });

        Artisan::call('tenant:run', [
            'tenant' => $tenant->getKey(),
            'artisan_command' => ['tenant:stub', 'Samuel'],
        ]);

        $this->assertSame($tenant->getKey(), TenantAwareCommandStub::$tenantId);
        $this->assertSame(['Samuel'], TenantAwareCommandStub::$arguments);
    }

    public function test_tenant_run_passes_through_options(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();

        Artisan::command('tenant:option-stub {--uppercase}', function (TenantManager $manager): void {
            TenantAwareCommandStub::$tenantId = optional($manager->getTenant())->getKey();
            TenantAwareCommandStub::$options['uppercase'] = (bool) $this->option('uppercase');
        });

        Artisan::call('tenant:run', [
            'tenant' => $tenant->slug,
            'artisan_command' => ['tenant:option-stub', '--uppercase'],
        ]);

        $this->assertSame($tenant->getKey(), TenantAwareCommandStub::$tenantId);
        $this->assertTrue(TenantAwareCommandStub::$options['uppercase']);
    }

    public function test_tenant_seed_runs_seeder_with_tenant_context(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();

        Artisan::call('tenant:seed', [
            'tenant' => $tenant->uuid,
            '--class' => TenantSeedCommandStub::class,
        ]);

        $this->assertSame($tenant->getKey(), TenantSeedCommandStub::$tenantId);
    }

    public function test_tenant_run_batch_outputs_json_summary(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();

        Artisan::command('tenant:noop', static fn (): int => \Illuminate\Console\Command::SUCCESS);

        $exitCode = Artisan::call('tenant:run-batch', [
            'artisan_command' => ['tenant:noop'],
            '--pretend' => true,
            '--format' => 'json',
        ]);

        $this->assertSame(\Illuminate\Console\Command::SUCCESS, $exitCode);

        $summary = $this->decodeSummaryFromArtisanOutput();

        $this->assertSame(1, $summary['processed']);
        $this->assertSame(1, $summary['succeeded']);
        $this->assertTrue($summary['pretend']);
        $this->assertSame([], $summary['failures']);
        $this->assertSame([], $summary['skipped_filters']);
    }

    public function test_tenant_run_batch_rejects_invalid_format(): void
    {
        \App\Models\Tenant::factory()->create();

        Artisan::command('tenant:noop', static fn (): int => \Illuminate\Console\Command::SUCCESS);

        $exitCode = Artisan::call('tenant:run-batch', [
            'artisan_command' => ['tenant:noop'],
            '--format' => 'xml',
        ]);

        $this->assertSame(\Illuminate\Console\Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Unsupported --format value [xml]', Artisan::output());
    }

    public function test_tenant_run_batch_requires_confirmation_when_requested(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();

        Artisan::command('tenant:batch-stub', function (TenantManager $manager): int {
            return TenantBatchCommandStub::handle($manager);
        });

        $this->artisan('tenant:run-batch', [
            'artisan_command' => ['tenant:batch-stub'],
            '--confirm' => true,
        ])
            ->expectsConfirmation('Execute [tenant:batch-stub] for 1 tenant(s)?', 'no')
            ->expectsOutput('Execution cancelled.')
            ->assertExitCode(\Illuminate\Console\Command::FAILURE);

        $this->assertSame([], TenantBatchCommandStub::$executedTenantIds);
    }

    public function test_tenant_run_batch_skips_prompt_when_yes_flag_provided(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();

        Artisan::command('tenant:batch-stub', function (TenantManager $manager): int {
            return TenantBatchCommandStub::handle($manager);
        });

        $exitCode = Artisan::call('tenant:run-batch', [
            'artisan_command' => ['tenant:batch-stub'],
            '--confirm' => true,
            '--yes' => true,
        ]);

        $this->assertSame(\Illuminate\Console\Command::SUCCESS, $exitCode);
        $this->assertSame([$tenant->id], TenantBatchCommandStub::$executedTenantIds);
    }

    public function test_tenant_run_batch_honours_stop_on_failure(): void
    {
        $tenants = \App\Models\Tenant::factory()->count(3)->create();

        Artisan::command('tenant:batch-stub', function (TenantManager $manager): int {
            return TenantBatchCommandStub::handle($manager);
        });

        TenantBatchCommandStub::$failOnTenantId = $tenants[1]->id;
        TenantBatchCommandStub::$failureExitCode = 7;

        $exitCode = Artisan::call('tenant:run-batch', [
            'artisan_command' => ['tenant:batch-stub'],
            '--stop-on-failure' => true,
        ]);

        $this->assertSame(\Illuminate\Console\Command::FAILURE, $exitCode);
        $this->assertSame([$tenants[0]->id, $tenants[1]->id], TenantBatchCommandStub::$executedTenantIds);

        $output = Artisan::output();
        $this->assertStringContainsString('Summary: processed 2, succeeded 1, failed 1', $output);
    }

    public function test_tenant_run_batch_reports_skipped_filters_in_json_summary(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();

        Artisan::command('tenant:batch-stub', function (TenantManager $manager): int {
            return TenantBatchCommandStub::handle($manager);
        });

        $exitCode = Artisan::call('tenant:run-batch', [
            'artisan_command' => ['tenant:batch-stub'],
            '--format' => 'json',
            '--tenant' => [$tenant->slug, 'missing-slug'],
        ]);

        $this->assertSame(\Illuminate\Console\Command::SUCCESS, $exitCode);

        $summary = $this->decodeSummaryFromArtisanOutput();

        $this->assertSame([$tenant->id], TenantBatchCommandStub::$executedTenantIds);
        $this->assertSame(['tenant:missing-slug'], $summary['skipped_filters']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeSummaryFromArtisanOutput(): array
    {
        $output = Artisan::output();
        $lines = array_values(array_filter(array_map('trim', explode(PHP_EOL, $output))));

        foreach (array_reverse($lines) as $line) {
            if ($line === '') {
                continue;
            }

            try {
                /** @var array<string, mixed> $decoded */
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

                return $decoded;
            } catch (JsonException $exception) {
                continue;
            }
        }

        $this->fail('Failed to decode JSON summary from Artisan output: ' . $output);

        return [];
    }
}

class TenantAwareCommandStub
{
    public static ?int $tenantId = null;

    /** @var array<int, mixed> */
    public static array $arguments = [];

    /** @var array<string, mixed> */
    public static array $options = [];

    public static function reset(): void
    {
        self::$tenantId = null;
        self::$arguments = [];
        self::$options = [];
    }
}

class TenantSeedCommandStub extends Seeder
{
    public static ?int $tenantId = null;

    public static function reset(): void
    {
        self::$tenantId = null;
    }

    public function run(): void
    {
        /** @var TenantManager $manager */
        $manager = app(TenantManager::class);
        self::$tenantId = optional($manager->getTenant())->getKey();
    }
}

class TenantBatchCommandStub
{
    /** @var array<int, int> */
    public static array $executedTenantIds = [];

    public static ?int $failOnTenantId = null;

    public static int $failureExitCode = 1;

    public static function reset(): void
    {
        self::$executedTenantIds = [];
        self::$failOnTenantId = null;
        self::$failureExitCode = 1;
    }

    public static function handle(TenantManager $manager): int
    {
        $tenantId = optional($manager->getTenant())->getKey();

        if ($tenantId !== null) {
            self::$executedTenantIds[] = $tenantId;
        }

        if ($tenantId !== null && self::$failOnTenantId === $tenantId) {
            return self::$failureExitCode;
        }

        return \Illuminate\Console\Command::SUCCESS;
    }
}
use JsonException;
