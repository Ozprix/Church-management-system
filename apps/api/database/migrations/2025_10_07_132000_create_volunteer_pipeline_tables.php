<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_signups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('volunteer_role_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('volunteer_team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('volunteer_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('volunteer_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->date('served_on');
            $table->decimal('hours', 5, 2);
            $table->string('source')->default('manual');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('volunteer_assignments', function (Blueprint $table): void {
            if (! Schema::hasColumn('volunteer_assignments', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('volunteer_assignments', 'confirmed_by')) {
                $table->foreignId('confirmed_by')->nullable()->after('confirmed_at')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('volunteer_assignments', function (Blueprint $table): void {
            if (Schema::hasColumn('volunteer_assignments', 'confirmed_by')) {
                $table->dropConstrainedForeignId('confirmed_by');
            }

            if (Schema::hasColumn('volunteer_assignments', 'confirmed_at')) {
                $table->dropColumn('confirmed_at');
            }
        });

        Schema::dropIfExists('volunteer_hours');
        Schema::dropIfExists('volunteer_signups');
    }
};
