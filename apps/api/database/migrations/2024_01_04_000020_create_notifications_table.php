<?php

use App\Models\Member;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(NotificationTemplate::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Member::class)->nullable()->constrained()->nullOnDelete();
            $table->enum('channel', ['sms', 'email']);
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->enum('status', ['queued', 'sending', 'sent', 'failed'])->default('queued');
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'channel']);
            $table->index(['tenant_id', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
