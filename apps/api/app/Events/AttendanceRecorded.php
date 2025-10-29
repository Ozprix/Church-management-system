<?php

namespace App\Events;

use App\Models\AttendanceRecord;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendanceRecorded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public AttendanceRecord $record)
    {
    }
}
