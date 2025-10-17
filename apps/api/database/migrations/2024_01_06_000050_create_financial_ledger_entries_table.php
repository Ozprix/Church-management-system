<?php

use App\Models\Donation;
use App\Models\Pledge;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Donation::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Pledge::class)->nullable()->constrained()->nullOnDelete();
            $table->enum('entry_type', ['debit', 'credit']);
            $table->string('account');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->dateTime('occurred_at');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['tenant_id', 'account']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_ledger_entries');
    }
};
