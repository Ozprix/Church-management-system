<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volunteer_roles', function (Blueprint $table): void {
            $afterColumn = Schema::hasColumn('volunteer_roles', 'metadata') ? 'metadata' : 'skills_required';

            if (! Schema::hasColumn('volunteer_roles', 'active_assignment_count')) {
                $table->unsignedInteger('active_assignment_count')->default(0)->after($afterColumn);
            }

            if (! Schema::hasColumn('volunteer_roles', 'pending_signup_count')) {
                $table->unsignedInteger('pending_signup_count')->default(0)->after('active_assignment_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('volunteer_roles', function (Blueprint $table): void {
            if (Schema::hasColumn('volunteer_roles', 'pending_signup_count')) {
                $table->dropColumn('pending_signup_count');
            }

            if (Schema::hasColumn('volunteer_roles', 'active_assignment_count')) {
                $table->dropColumn('active_assignment_count');
            }
        });
    }
};
