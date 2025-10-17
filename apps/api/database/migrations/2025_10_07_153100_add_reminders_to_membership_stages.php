<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_process_stages', function (Blueprint $table): void {
            if (! Schema::hasColumn('membership_process_stages', 'reminder_minutes')) {
                $table->unsignedInteger('reminder_minutes')->nullable()->after('exit_actions');
            }

            if (! Schema::hasColumn('membership_process_stages', 'reminder_template_id')) {
                $table->foreignId('reminder_template_id')
                    ->nullable()
                    ->after('reminder_minutes')
                    ->constrained('notification_templates')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('membership_process_stages', function (Blueprint $table): void {
            if (Schema::hasColumn('membership_process_stages', 'reminder_template_id')) {
                $table->dropConstrainedForeignId('reminder_template_id');
            }

            if (Schema::hasColumn('membership_process_stages', 'reminder_minutes')) {
                $table->dropColumn('reminder_minutes');
            }
        });
    }
};
