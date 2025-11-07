<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pledges', function (Blueprint $table): void {
            $table->boolean('reminder_enabled')->default(false)->after('metadata');
            $table->string('reminder_cadence')->nullable()->after('reminder_enabled');
            $table->timestamp('next_reminder_at')->nullable()->after('reminder_cadence');
            $table->timestamp('last_reminder_sent_at')->nullable()->after('next_reminder_at');

            $table->index(['tenant_id', 'reminder_enabled', 'next_reminder_at'], 'pledges_reminder_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('pledges', function (Blueprint $table): void {
            $table->dropIndex('pledges_reminder_lookup');

            $table->dropColumn([
                'reminder_enabled',
                'reminder_cadence',
                'next_reminder_at',
                'last_reminder_sent_at',
            ]);
        });
    }
};
