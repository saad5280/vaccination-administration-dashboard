<?php

return [
    'duplicate_detection_fields' => ['name', 'date_of_birth', 'guardian_phone'],
    'child_photo_max_kilobytes' => 2048,
    'duplicate_review_minutes' => 15,
    'backup_disk' => env('BACKUP_DISK', 'local'),
    'backup_retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
];
