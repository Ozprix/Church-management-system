<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildTableForSqlite([
                'text',
                'number',
                'date',
                'boolean',
                'select',
                'multi_select',
                'file',
                'signature',
            ]);

            return;
        }

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
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildTableForSqlite([
                'text',
                'number',
                'date',
                'boolean',
                'select',
                'multi_select',
            ], down: true);

            return;
        }

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

    private function rebuildTableForSqlite(array $allowedTypes, bool $down = false): void
    {
        DB::statement('PRAGMA foreign_keys=OFF;');

        Schema::dropIfExists('member_custom_fields_new');

        Schema::create('member_custom_fields_new', function (Blueprint $table) use ($allowedTypes): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 150);
            $table->enum('data_type', $allowedTypes);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
        });

        $records = DB::table('member_custom_fields')->orderBy('id')->get();

        foreach ($records as $record) {
            $data = (array) $record;

            if ($down && ! in_array($record->data_type, $allowedTypes, true)) {
                $data['data_type'] = 'text';
            }

            DB::table('member_custom_fields_new')->insert($data);
        }

        Schema::drop('member_custom_fields');
        Schema::rename('member_custom_fields_new', 'member_custom_fields');

        DB::statement('PRAGMA foreign_keys=ON;');
    }
};
