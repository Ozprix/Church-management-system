<?php

use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('volunteer_role_team', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('volunteer_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('volunteer_team_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['volunteer_role_id', 'volunteer_team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_role_team');
        Schema::dropIfExists('volunteer_teams');
    }
};
