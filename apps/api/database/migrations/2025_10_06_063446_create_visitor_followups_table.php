<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('visitor_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('workflow_id')->constrained('visitor_workflows')->cascadeOnDelete();
            $table->foreignId('current_step_id')->nullable()->constrained('visitor_workflow_steps')->nullOnDelete();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'halted'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_step_run_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'workflow_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_followups');
    }
};
