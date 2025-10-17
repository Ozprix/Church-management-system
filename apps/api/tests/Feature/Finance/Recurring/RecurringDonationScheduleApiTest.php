<?php

declare(strict_types=1);

namespace Tests\Feature\Finance\Recurring;

use App\Jobs\ProcessRecurringDonationJob;
use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecurringDonationScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_schedule_and_dispatches_job(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
       $paymentMethod = PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'member_id' => $member->id,
            'payment_method_id' => $paymentMethod->id,
            'frequency' => 'monthly',
            'amount' => 50,
            'currency' => 'USD',
            'starts_on' => now()->toDateString(),
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/recurring-donations', $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('recurring_donation_schedules', [
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'payment_method_id' => $paymentMethod->id,
            'status' => 'active',
        ]);

        Queue::assertPushed(ProcessRecurringDonationJob::class);
    }
}
