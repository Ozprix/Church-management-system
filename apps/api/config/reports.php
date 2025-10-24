<?php

return [
    'directory' => env('FINANCE_REPORTS_DIRECTORY', 'finance'),
    'pdf_config' => [
        'default_font' => 'dejavusans',
        'tempDir' => storage_path('app/reports/tmp'),
    ],
    'download_ttl' => env('FINANCE_REPORT_DOWNLOAD_TTL', 5),
    'branding' => [
        'primary_color' => env('FINANCE_REPORT_PRIMARY_COLOR', '#111827'),
        'accent_color' => env('FINANCE_REPORT_ACCENT_COLOR', '#047857'),
    ],
];
