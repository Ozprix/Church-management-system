<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_processes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_start_on_member_create')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('membership_process_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('process_id')->constrained('membership_processes')->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->unsignedInteger('step_order')->default(0);
            $table->json('entry_actions')->nullable();
            $table->json('exit_actions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['process_id', 'key']);
            $table->index(['process_id', 'step_order']);
        });

        Schema::create('member_process_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('process_id')->constrained('membership_processes')->cascadeOnDelete();
            $table->foreignId('current_stage_id')->nullable()->constrained('membership_process_stages')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('halted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'member_id', 'process_id'], 'member_process_unique');
        });

        Schema::create('membership_process_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('process_run_id')->constrained('member_process_runs')->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('membership_process_stages')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_process_logs');
        Schema::dropIfExists('member_process_runs');
        Schema::dropIfExists('membership_process_stages');
        Schema::dropIfExists('membership_processes');
    }
};
