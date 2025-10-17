<?php

return [
    'directory' => env('FINANCE_REPORTS_DIRECTORY', 'finance'),
    'pdf_config' => [
        'default_font' => 'dejavusans',
        'tempDir' => storage_path('app/reports/tmp'),
    ],
];
