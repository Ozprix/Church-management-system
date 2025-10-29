<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_analytics_report_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_analytics_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('reports');
            $table->string('file_path');
            $table->string('format')->default('csv');
            $table->json('filters')->nullable();
            $table->timestamp('generated_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_analytics_report_snapshots');
    }
};

