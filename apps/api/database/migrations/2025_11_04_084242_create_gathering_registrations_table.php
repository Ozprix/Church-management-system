<?php

use App\Models\Gathering;
use App\Models\GatheringTicketType;
use App\Models\Member;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gathering_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Gathering::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(GatheringTicketType::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Member::class)->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->dateTime('checked_in_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'gathering_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gathering_registrations');
    }
};
