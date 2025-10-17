<?php

use App\Models\Gathering;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\VolunteerRole;
use App\Models\VolunteerTeam;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Member::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(VolunteerRole::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(VolunteerTeam::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Gathering::class)->nullable()->constrained()->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->enum('status', ['scheduled', 'confirmed', 'swapped', 'cancelled'])->default('scheduled');
            $table->json('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'member_id']);
            $table->index(['tenant_id', 'starts_at']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_assignments');
    }
};
