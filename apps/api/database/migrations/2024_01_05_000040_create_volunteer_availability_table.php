<?php

use App\Models\Member;
use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_availability', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Member::class)->constrained()->cascadeOnDelete();
            $table->json('weekdays')->nullable();
            $table->json('time_blocks')->nullable();
            $table->date('unavailable_from')->nullable();
            $table->date('unavailable_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_availability');
    }
};
