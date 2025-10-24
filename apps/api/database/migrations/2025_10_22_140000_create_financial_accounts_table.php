<?php

use App\Models\Tenant;
use App\Services\Finance\ChartOfAccountsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'type']);
        });

        Schema::table('financial_ledger_entries', function (Blueprint $table): void {
            $table->foreignId('financial_account_id')
                ->nullable()
                ->after('pledge_id')
                ->constrained('financial_accounts')
                ->nullOnDelete();
        });

        app(ChartOfAccountsService::class)->ensureDefaultsForAllTenants();
    }

    public function down(): void
    {
        Schema::table('financial_ledger_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('financial_account_id');
        });

        Schema::dropIfExists('financial_accounts');
    }
};
