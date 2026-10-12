<?php

return [
    'upload_max_kb' => (int) env('ARCHIVE_UPLOAD_MAX_KB', 20480),
    'upload_max_files' => (int) env('ARCHIVE_UPLOAD_MAX_FILES', 20),
];
