<?php

namespace App\Console;

use App\Console\Commands\ProcessTrialExpirationsCommand;
use App\Console\Commands\RunRecurringDonations;
use App\Console\Commands\RunVisitorFollowups;
use App\Console\Commands\SendFamilyRemindersCommand;
use App\Console\Commands\SendMemberStaleProfilesAlertCommand;
use App\Console\Commands\RunMemberAnalyticsReportsCommand;
use App\Console\Commands\SendVisitorOverdueRemindersCommand;
use App\Console\Commands\TenantRunBatchCommand;
use App\Console\Commands\TenantRunCommand;
use App\Console\Commands\TenantSeedCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        RunVisitorFollowups::class,
        RunRecurringDonations::class,
        ProcessTrialExpirationsCommand::class,
        SendFamilyRemindersCommand::class,
        SendMemberStaleProfilesAlertCommand::class,
        RunMemberAnalyticsReportsCommand::class,
        SendVisitorOverdueRemindersCommand::class,
        TenantRunBatchCommand::class,
        TenantRunCommand::class,
        TenantSeedCommand::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('visitor:followups-run')->everyFifteenMinutes();
        $schedule->command('finance:recurring-run')->hourly();
        $schedule->command('billing:process-trials')->dailyAt('00:30');
        $schedule->command('families:send-reminders')->dailyAt('08:00');
        $schedule->command('members:send-stale-alerts')->dailyAt('07:30');
        $schedule->command('members:run-saved-reports')->dailyAt('06:00');
        $schedule->command('visitors:send-overdue-reminders')->dailyAt('09:00');
    }
}
