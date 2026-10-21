<?php

/*
| Өгөгдлийн сангийн нөөцлөлт (php artisan db:backup / db:restore).
| Windows Task Scheduler өдөр бүр BACKUP_TIME-д db:backup-ийг ажиллуулна.
*/
return [
    // mysqldump.exe, mysql.exe байрлах хавтас
    'mysql_bin' => env('BACKUP_MYSQL_BIN', 'C:/xampp/mysql/bin'),

    // Нөөц хадгалах хавтас
    'path' => env('BACKUP_PATH', storage_path('app'.DIRECTORY_SEPARATOR.'backups')),

    // Нэмэлт хуулбар: өөр диск, гадаад диск, сүлжээний хавтас (хоосон бол хуулахгүй).
    // Нөөц өгөгдлийн сантай ижил дискэнд л байвал диск эвдрэхэд хамт устна.
    'copy_to' => env('BACKUP_COPY_PATH'),

    // Хэдэн өдрийн нөөц хадгалах (түүнээс хуучныг автоматаар устгана)
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),
];
