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
    /**
     * Эцэг классын (Laravel) гарын үсэг төрөлгүй тул энд төрөл нэмэх боломжгүй (PHP fatal error) — PHPDoc-оор тодруулна.
     *
     * @param  string  $dsn
     * @param  string|null  $username
     * @param  string|null  $password
     * @param  array<int, mixed>  $options
     * @return \PDO
     */
    protected function tryAgainIfCausedByLostConnection(Throwable $e, $dsn, $username, $password, $options)
    {
        throw $e;
    }
}
