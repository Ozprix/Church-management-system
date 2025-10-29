<?php

namespace App\Providers;

use App\Events\AttendanceRecorded;
use App\Listeners\ProcessAttendanceAutomations;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        AttendanceRecorded::class => [
            ProcessAttendanceAutomations::class,
        ],
    ];
}
