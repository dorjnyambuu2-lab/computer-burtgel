<?php

return [
    /*
    | Супер админы анхны үндсэн нууц үг. Seeder үүгээр Супер админыг үүсгэнэ;
    | энэ нууц үгээр нэвтэрсэн хэн ч бай шинэ нууц үг солихоос өөр юу ч хийж чадахгүй.
    */
    'superadmin_default_password' => env('SUPERADMIN_DEFAULT_PASSWORD', 'superadmin123'),

    /*
    | Супер админ нууц үгээ сэргээхэд имэйлийн холбоосоос гадна асуух сэргээх түлхүүр.
    | Хоосон бол зөвхөн имэйлийн холбоосоор сэргээнэ. Серверээс:
    | php artisan superadmin:reset-password
    */
    'superadmin_recovery_key' => env('SUPERADMIN_RECOVERY_KEY'),

    // Засварын дарааллын өдөр тутмын тайланг админуудад илгээх цаг
    'repair_digest_time' => env('REPAIR_DIGEST_TIME', '09:00'),
];
