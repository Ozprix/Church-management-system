<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table): void {
            $table->string('receipt_disk')->nullable()->after('provider_reference');
            $table->string('receipt_path')->nullable()->after('receipt_disk');
            $table->timestamp('receipt_generated_at')->nullable()->after('receipt_path');
            $table->timestamp('receipt_sent_at')->nullable()->after('receipt_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table): void {
            $table->dropColumn(['receipt_disk', 'receipt_path', 'receipt_generated_at', 'receipt_sent_at']);
        });
    }
};
