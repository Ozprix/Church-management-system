<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('middle_name', 120)->nullable();
            $table->string('preferred_name', 120)->nullable();
            $table->enum('gender', ['male', 'female', 'non_binary', 'unspecified'])->nullable();
            $table->date('dob')->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed', 'separated', 'partnered'])->nullable();
            $table->enum('membership_status', ['prospect', 'active', 'inactive', 'visitor', 'suspended', 'transferred'])->default('prospect');
            $table->string('membership_stage', 150)->nullable();
            $table->dateTime('joined_at')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'membership_status']);
            $table->index(['tenant_id', 'last_name']);
            $table->index(['tenant_id', 'uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
