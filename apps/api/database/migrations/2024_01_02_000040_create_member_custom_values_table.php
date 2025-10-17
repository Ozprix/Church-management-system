<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_custom_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('member_custom_fields')->cascadeOnDelete();
            $table->string('value_string', 255)->nullable();
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 15, 2)->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'field_id']);
            $table->index(['tenant_id', 'field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_custom_values');
    }
};
