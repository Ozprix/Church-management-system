<?php

return [
    'disk' => env('ATTENDANCE_REPORTS_DISK', 'reports'),
    'directory' => env('ATTENDANCE_REPORTS_DIRECTORY', 'attendance'),
    'download_ttl_days' => env('ATTENDANCE_REPORTS_DOWNLOAD_TTL', 7),
];

