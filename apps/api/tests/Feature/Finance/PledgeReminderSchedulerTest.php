<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Jobs\SendNotificationJob;
use App\Jobs\SendPledgeReminderJob;
use App\Models\Fund;
use App\Models\Member;
use App\Models\MemberContact;
use App\Models\Pledge;
use App\Models\Tenant;
use App\Services\NotificationService;
use App\Support\PledgeReminderCadence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PledgeReminderSchedulerTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_jobs_for_due_pledges(): void
    {
        config(['queue.default' => 'database']);
        $originalQueue = app('queue');
        Queue::fake();

        try {
            $tenant = Tenant::factory()->create();
            $member = Member::factory()->create(['tenant_id' => $tenant->id]);
            $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);

            MemberContact::factory()->forMember($member)->state([
                'type' => 'email',
                'value' => 'member@example.com',
            ])->create();

            $duePledge = Pledge::factory()->create([
                'tenant_id' => $tenant->id,
                'member_id' => $member->id,
                'fund_id' => $fund->id,
                'status' => 'active',
                'reminder_enabled' => true,
                'reminder_cadence' => PledgeReminderCadence::DAILY,
                'next_reminder_at' => Carbon::now()->subDay(),
            ]);

            Pledge::factory()->create([
                'tenant_id' => $tenant->id,
                'member_id' => $member->id,
                'fund_id' => $fund->id,
                'status' => 'active',
                'reminder_enabled' => true,
                'reminder_cadence' => PledgeReminderCadence::DAILY,
                'next_reminder_at' => Carbon::now()->addDay(),
            ]);

            $result = Artisan::call('pledges:send-reminders');

            $this->assertSame(0, $result);

            Queue::assertPushed(SendPledgeReminderJob::class, function (SendPledgeReminderJob $job) use ($duePledge) {
                return $job->getPledgeId() === $duePledge->id;
            });

            Queue::assertPushed(SendPledgeReminderJob::class, 1);
        } finally {
            config(['queue.default' => 'sync']);
            Queue::swap($originalQueue);
        }
    }

    public function test_command_can_limit_to_specific_tenant_and_job_count(): void
    {
        config(['queue.default' => 'database']);
        $originalQueue = app('queue');
        Queue::fake();

        try {
            $tenantA = Tenant::factory()->create();
            $tenantB = Tenant::factory()->create();
            $memberA = Member::factory()->create(['tenant_id' => $tenantA->id]);
            $memberB = Member::factory()->create(['tenant_id' => $tenantB->id]);
            $fundA = Fund::factory()->create(['tenant_id' => $tenantA->id]);
            $fundB = Fund::factory()->create(['tenant_id' => $tenantB->id]);

            Pledge::factory()->create([
                'tenant_id' => $tenantA->id,
                'member_id' => $memberA->id,
                'fund_id' => $fundA->id,
                'status' => 'active',
                'reminder_enabled' => true,
                'reminder_cadence' => PledgeReminderCadence::DAILY,
                'next_reminder_at' => Carbon::now()->subDay(),
            ]);

            $dueForB = Pledge::factory()->create([
                'tenant_id' => $tenantB->id,
                'member_id' => $memberB->id,
                'fund_id' => $fundB->id,
                'status' => 'active',
                'reminder_enabled' => true,
                'reminder_cadence' => PledgeReminderCadence::DAILY,
                'next_reminder_at' => Carbon::now()->subDay(),
            ]);

            Pledge::factory()->count(2)->create([
                'tenant_id' => $tenantB->id,
                'member_id' => $memberB->id,
                'fund_id' => $fundB->id,
                'status' => 'active',
                'reminder_enabled' => true,
                'reminder_cadence' => PledgeReminderCadence::DAILY,
                'next_reminder_at' => Carbon::now()->subDay(),
            ]);

            Artisan::call('pledges:send-reminders', ['--tenant' => $tenantB->id, '--limit' => 1]);

            Queue::assertPushed(SendPledgeReminderJob::class, 1);
            Queue::assertPushed(SendPledgeReminderJob::class, function (SendPledgeReminderJob $job) use ($dueForB) {
                return $job->getPledgeId() === $dueForB->id;
            });
        } finally {
            config(['queue.default' => 'sync']);
            Queue::swap($originalQueue);
        }
    }

    public function test_job_queues_notifications_and_advances_schedule(): void
    {
        $originalQueue = app('queue');
        Queue::fake();

        try {
            $tenant = Tenant::factory()->create(['timezone' => 'UTC']);
            $member = Member::factory()->create(['tenant_id' => $tenant->id]);
            $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);

            MemberContact::factory()->forMember($member)->state([
                'type' => 'email',
                'value' => 'member@example.com',
            ])->create();

            MemberContact::factory()->forMember($member)->state([
                'type' => 'mobile',
                'value' => '+15550001111',
            ])->create();

            $pledge = Pledge::factory()->create([
                'tenant_id' => $tenant->id,
                'member_id' => $member->id,
                'fund_id' => $fund->id,
                'amount' => 500,
                'fulfilled_amount' => 150,
                'status' => 'active',
                'reminder_enabled' => true,
                'reminder_cadence' => PledgeReminderCadence::WEEKLY,
                'next_reminder_at' => Carbon::now()->subDay(),
            ]);

            $job = new SendPledgeReminderJob($pledge->id);

            $job->handle(app(NotificationService::class));

            $pledge->refresh();

            $this->assertNotNull($pledge->last_reminder_sent_at);
            $this->assertNotNull($pledge->next_reminder_at);
            $this->assertTrue($pledge->next_reminder_at->gt(Carbon::now()));

            $this->assertDatabaseCount('notifications', 2);

            Queue::assertPushed(SendNotificationJob::class, 2);
        } finally {
            Queue::swap($originalQueue);
        }
    }

    public function test_job_turns_off_reminders_when_pledge_fulfilled(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);

        $pledge = Pledge::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'fund_id' => $fund->id,
            'amount' => 100,
            'fulfilled_amount' => 100,
            'status' => 'active',
            'reminder_enabled' => true,
            'reminder_cadence' => PledgeReminderCadence::MONTHLY,
            'next_reminder_at' => Carbon::now()->subDay(),
        ]);

        $job = new SendPledgeReminderJob($pledge->id);
        $job->handle(app(NotificationService::class));

        $pledge->refresh();

        $this->assertFalse($pledge->reminder_enabled);
        $this->assertNull($pledge->reminder_cadence);
        $this->assertNull($pledge->next_reminder_at);
    }
}
