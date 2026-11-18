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

    /*
    | Shell-гүй орчинд (Render-ийн үнэгүй багц) Супер админы нууц үгийг сэргээх: тохируулсан бол сервер асахад
    | `superadmin:reset-from-env` энэ нууц үгийг тавьж, нэвтэрмэгц солиулна. Ашигласны дараа заавал хоослоно —
    | эс бөгөөс сервер дахин асах бүрт нууц үг энэ утга руу буцна.
    */
    'superadmin_reset_password' => env('SUPERADMIN_RESET_PASSWORD'),

    // Засварын дарааллын өдөр тутмын тайланг админуудад илгээх цаг
    'repair_digest_time' => env('REPAIR_DIGEST_TIME', '09:00'),
];
