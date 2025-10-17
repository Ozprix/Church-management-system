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
        Schema::table('member_custom_values', function (Blueprint $table) {
            $table->string('value_file_disk')->nullable()->after('value_json');
            $table->string('value_file_path')->nullable()->after('value_file_disk');
            $table->string('value_file_name')->nullable()->after('value_file_path');
            $table->string('value_file_mime')->nullable()->after('value_file_name');
            $table->unsignedBigInteger('value_file_size')->nullable()->after('value_file_mime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_custom_values', function (Blueprint $table) {
            $table->dropColumn([
                'value_file_disk',
                'value_file_path',
                'value_file_name',
                'value_file_mime',
                'value_file_size',
            ]);
        });
    }
};
