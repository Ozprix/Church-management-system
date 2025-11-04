<?php

use App\Models\Expense;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_ledger_entries', function (Blueprint $table): void {
            $table->foreignIdFor(Expense::class)
                ->nullable()
                ->after('pledge_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['tenant_id', 'expense_id'], 'financial_ledger_entries_expense_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('financial_ledger_entries', function (Blueprint $table): void {
            $table->dropIndex('financial_ledger_entries_expense_lookup');
            $table->dropConstrainedForeignId('expense_id');
        });
    }
};
