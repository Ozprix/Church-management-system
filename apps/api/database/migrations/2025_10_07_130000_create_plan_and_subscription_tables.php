<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('monthly_price', 10, 2)->nullable();
            $table->decimal('annual_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->json('limits')->nullable();
            $table->timestamps();
        });

        Schema::create('plan_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('feature');
            $table->unsignedInteger('limit')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'feature']);
        });

        Schema::create('tenant_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('status')->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->unsignedInteger('seat_limit')->nullable();
            $table->unsignedInteger('seat_usage')->default(0);
            $table->json('limits_override')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'status'], 'tenant_plan_status_unique');
        });

        Schema::create('tenant_plan_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('feature');
            $table->unsignedBigInteger('used')->default(0);
            $table->unsignedBigInteger('limit')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'feature']);
        });

        Schema::table('tenant_domains', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_domains', 'verification_token')) {
                $table->string('verification_token')->nullable()->after('hostname');
            }

            if (! Schema::hasColumn('tenant_domains', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verification_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_domains', function (Blueprint $table): void {
            if (Schema::hasColumn('tenant_domains', 'verification_token')) {
                $table->dropColumn('verification_token');
            }

            if (Schema::hasColumn('tenant_domains', 'verified_at')) {
                $table->dropColumn('verified_at');
            }
        });

        Schema::dropIfExists('tenant_plan_usages');
        Schema::dropIfExists('tenant_plans');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
    }
};
