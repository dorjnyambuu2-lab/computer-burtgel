<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// .env-ийн утгыг зөвхөн тухайн хүсэлтийн $_SERVER, $_ENV-д уншина (putenv ашиглахгүй).
// XAMPP-ийн Apache олон урсгалтай (thread) тул putenv нь бүх хүсэлтэд нийтлэг: нэг хүсэлт дуусахад PHP орчны
// хувьсагчийг цэвэрлэж, зэрэг явж буй өөр хүсэлтийн APP_KEY, DB_* алга болж санамсаргүй 500 алдаа гардаг байсан.
Illuminate\Support\Env::disablePutenv();

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
