<?php

return [
    // Disk that receives the backup files. "local" keeps them on the server (storage/app/backups); point it at an
    // S3-compatible disk (config/filesystems.php) to keep copies off the machine.
    'disk' => env('BACKUP_DISK', 'local'),
    'path' => 'backups',

    // How many backups to keep; older ones are deleted after each run.
    'keep' => (int) env('BACKUP_KEEP', 14),
];
