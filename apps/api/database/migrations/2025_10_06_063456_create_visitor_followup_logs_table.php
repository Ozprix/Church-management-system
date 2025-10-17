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
        Schema::create('visitor_followup_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('followup_id')->constrained('visitor_followups')->cascadeOnDelete();
            $table->foreignId('step_id')->nullable()->constrained('visitor_workflow_steps')->nullOnDelete();
            $table->enum('status', ['queued', 'sent', 'failed', 'skipped'])->default('queued');
            $table->enum('channel', ['email', 'sms', 'task'])->nullable();
            $table->timestamp('run_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['followup_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_followup_logs');
    }
};
