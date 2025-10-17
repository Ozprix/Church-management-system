<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<SQL
            ALTER TABLE member_custom_fields
            MODIFY COLUMN data_type ENUM(
                'text',
                'number',
                'date',
                'boolean',
                'select',
                'multi_select',
                'file',
                'signature'
            ) NOT NULL;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(<<<SQL
            ALTER TABLE member_custom_fields
            MODIFY COLUMN data_type ENUM(
                'text',
                'number',
                'date',
                'boolean',
                'select',
                'multi_select'
            ) NOT NULL;
        SQL);
    }
};
