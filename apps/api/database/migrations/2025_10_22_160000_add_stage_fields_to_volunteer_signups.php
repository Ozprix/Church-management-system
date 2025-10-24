<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volunteer_signups', function (Blueprint $table): void {
            $table->string('stage')->default('applied')->after('status');
            $table->json('stage_history')->nullable()->after('stage');
            $table->timestamp('last_contacted_at')->nullable()->after('confirmed_at');
            $table->timestamp('follow_up_at')->nullable()->after('last_contacted_at');
            $table->json('onboarding_checklist')->nullable()->after('metadata');
        });

        Schema::table('volunteer_roles', function (Blueprint $table): void {
            if (! Schema::hasColumn('volunteer_roles', 'pipeline_stage_counts')) {
                $table->json('pipeline_stage_counts')->nullable()->after('pending_signup_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('volunteer_signups', function (Blueprint $table): void {
            $table->dropColumn(['stage', 'stage_history', 'last_contacted_at', 'follow_up_at', 'onboarding_checklist']);
        });

        Schema::table('volunteer_roles', function (Blueprint $table): void {
            if (Schema::hasColumn('volunteer_roles', 'pipeline_stage_counts')) {
                $table->dropColumn('pipeline_stage_counts');
            }
        });
    }
};
