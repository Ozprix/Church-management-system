<?php

return [
    'disk' => env('CUSTOM_FIELD_FILES_DISK', 'custom_fields'),
    'upload_directory' => env('CUSTOM_FIELD_FILES_DIRECTORY', 'custom-fields'),
    'url_expires' => (int) env('CUSTOM_FIELD_FILE_URL_TTL', 5),
];
