<?php

namespace App\Support;

use Illuminate\Database\Connectors\MySqlConnector;
use Throwable;

/**
 * Вэб хүсэлтэд MySQL-тэй анх холбогдож чадаагүй бол ДАХИН ОРОЛДОХГҮЙ.
 * Laravel-ийн анхны connector «холболт тасарсан» төрлийн алдаанд дахин оролддог тул MySQL гацсан/унтарсан үед
 * нэг хүсэлт олон удаа хүлээж (4 × timeout), хуудас 40 сек гацдаг байсан. Query дундуур тасарсан холболтыг
 * дахин холбох Connection-ий хамгаалалт хэвээр ажиллана. Artisan командад хэрэглэхгүй (AppServiceProvider).
 */
class FastFailMySqlConnector extends MySqlConnector
{
    protected function tryAgainIfCausedByLostConnection(Throwable $e, $dsn, $username, $password, $options)
    {
        throw $e;
    }
}
