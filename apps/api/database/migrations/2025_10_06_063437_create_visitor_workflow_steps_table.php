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
        Schema::create('visitor_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('visitor_workflows')->cascadeOnDelete();
            $table->unsignedInteger('step_number');
            $table->string('name')->nullable();
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->enum('channel', ['email', 'sms', 'task'])->default('email');
            $table->foreignId('notification_template_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['workflow_id', 'step_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_workflow_steps');
    }
};
