<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);

        // Content-Length: хариу илгээсний дараах ажлыг (deferred имэйл) хөтөч хүлээхгүй
        $middleware->append(\App\Http\Middleware\SetContentLength::class);

        // Хайлт, шүүлтүүрийн query параметрт массив ирвэл хасна (?q[]=x → 500 алдаанаас сэргийлнэ)
        $middleware->prependToGroup('web', \App\Http\Middleware\ScalarQueryParams::class);

        // Хэрэглэгч онлайн эсэхийг тодорхойлох «сүүлд харагдсан» цаг (доорх чиглүүлэлтүүдээс өмнө ажиллана)
        $middleware->appendToGroup('web', \App\Http\Middleware\TrackLastSeen::class);

        // Ажилтан зөвхөн өөрийн хяналтын самбар руу хандана
        $middleware->appendToGroup('web', \App\Http\Middleware\RestrictEmployee::class);

        // Анхны / түр нууц үгтэй бол эхлээд солиулна
        $middleware->appendToGroup('web', \App\Http\Middleware\ForcePasswordChange::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();