<?php

use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('short_code', 20)->nullable();
            $table->text('description')->nullable();
            $table->string('default_location')->nullable();
            $table->time('default_start_time')->nullable();
            $table->unsignedInteger('default_duration_minutes')->nullable();
            $table->unsignedTinyInteger('absence_threshold')->default(3);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'short_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
