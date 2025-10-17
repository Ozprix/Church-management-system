<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volunteer_assignments', function (Blueprint $table): void {
            $table->string('repeat_frequency')->nullable()->after('notes');
            $table->timestamp('repeat_until')->nullable()->after('repeat_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('volunteer_assignments', function (Blueprint $table): void {
            $table->dropColumn(['repeat_frequency', 'repeat_until']);
        });
    }
};
