<?php

return [
    'demo_mode' => env('DEMO_MODE', false),
    'demo_accounts' => [
        [
            'name' => 'Demo Administrator',
            'email' => 'admin@demo.immunicare.test',
            'password' => 'DemoAdmin2026!',
            'role' => 'administrator',
            'label' => 'Administrator',
        ],
        [
            'name' => 'Demo Vaccinator',
            'email' => 'vaccinator@demo.immunicare.test',
            'password' => 'DemoVaccinator2026!',
            'role' => 'vaccinator',
            'label' => 'Vaccinator',
        ],
        [
            'name' => 'Demo Registration Officer',
            'email' => 'registration@demo.immunicare.test',
            'password' => 'DemoRegistration2026!',
            'role' => 'registration',
            'label' => 'Registration',
        ],
        [
            'name' => 'Demo Manager',
            'email' => 'manager@demo.immunicare.test',
            'password' => 'DemoManager2026!',
            'role' => 'manager',
            'label' => 'Manager',
        ],
        [
            'name' => 'Demo Inventory Officer',
            'email' => 'inventory@demo.immunicare.test',
            'password' => 'DemoInventory2026!',
            'role' => 'inventory-officer',
            'label' => 'Inventory Officer',
        ],
    ],
    'duplicate_detection_fields' => ['name', 'date_of_birth', 'guardian_phone'],
    'child_photo_max_kilobytes' => 2048,
    'duplicate_review_minutes' => 15,
    'backup_disk' => env('BACKUP_DISK', 'local'),
    'backup_retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
];
