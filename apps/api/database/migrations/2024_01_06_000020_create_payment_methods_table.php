<?php

use App\Models\Member;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Member::class)->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['card', 'bank', 'mobile_money', 'cash', 'other'])->default('card');
            $table->string('brand')->nullable();
            $table->string('last_four', 4)->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'member_id']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
