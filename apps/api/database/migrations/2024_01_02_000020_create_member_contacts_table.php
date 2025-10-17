<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->enum('type', ['email', 'mobile', 'home_phone', 'address', 'social', 'other']);
            $table->string('label', 120)->nullable();
            $table->string('value', 512);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_emergency')->default(false);
            $table->enum('communication_preference', ['email', 'sms', 'call', 'mail'])->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_contacts');
    }
};
