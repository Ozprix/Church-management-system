<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('trigger_type');
            $table->json('trigger_config')->nullable();
            $table->string('channel');
            $table->foreignId('notification_template_id')->nullable()->constrained()->nullOnDelete();
            $table->json('delivery_config')->nullable();
            $table->string('status')->default('inactive');
            $table->unsignedInteger('throttle_minutes')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('notification_rule_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_rule_id')->constrained()->cascadeOnDelete();
            $table->timestamp('ran_at');
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->string('status')->default('completed');
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('collected_on');
            $table->string('channel');
            $table->unsignedInteger('queued')->default(0);
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('opened')->default(0);
            $table->unsignedInteger('clicked')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'collected_on', 'channel'], 'notification_metrics_unique');
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->nullableMorphs('auditable');
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notification_metrics');
        Schema::dropIfExists('notification_rule_runs');
        Schema::dropIfExists('notification_rules');
    }
};
