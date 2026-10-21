<?php

return [
    'restic_binary' => env('INSURANCE_BACKUP_RESTIC', 'restic'),
    'dump_binary' => env('INSURANCE_BACKUP_MYSQLDUMP', 'mysqldump'),
    'mysql_binary' => env('INSURANCE_BACKUP_MYSQL', 'mysql'),
    'repository' => env('INSURANCE_BACKUP_REPOSITORY'),
    'password_file' => env('INSURANCE_BACKUP_PASSWORD_FILE'),
    'work_directory' => env('INSURANCE_BACKUP_WORK_DIRECTORY'),
    'timeout_seconds' => 7200,
    'disks' => ['attachments', 'local', 'public'],
];
