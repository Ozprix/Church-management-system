<?php

declare(strict_types=1);

namespace Tests\Feature\Finance\Recurring;

use App\Jobs\ProcessRecurringDonationJob;
use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\RecurringDonationSchedule;
use App\Models\Tenant;
use App\Services\RecurringDonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecurringDonationProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedules_are_processed_into_donations(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $paymentMethod = PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);

        $schedule = RecurringDonationSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'payment_method_id' => $paymentMethod->id,
            'frequency' => 'monthly',
            'amount' => 25.00,
            'status' => 'active',
            'starts_on' => now()->subMonth(),
            'next_run_at' => now()->subDay(),
        ]);

        $service = app(RecurringDonationService::class);
        $attempt = $service->processSchedule($schedule);

        $this->assertEquals('succeeded', $attempt->status);
        $this->assertNotNull($attempt->donation_id);
        $this->assertDatabaseHas('donations', [
            'id' => $attempt->donation_id,
            'tenant_id' => $tenant->id,
            'amount' => 25.00,
            'status' => 'succeeded',
        ]);
    }

    public function test_command_dispatches_due_schedules(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        RecurringDonationSchedule::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'next_run_at' => Carbon::now()->subMinutes(5),
        ]);

        $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->artisan('finance:recurring-run')
            ->assertExitCode(0);

        Queue::assertPushed(ProcessRecurringDonationJob::class);
    }
}
