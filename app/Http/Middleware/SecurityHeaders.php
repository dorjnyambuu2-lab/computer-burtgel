<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Хөтчийн хамгаалалтын толгойнууд (бүх хариунд, алдааны хуудсанд ч):
 *  - X-Frame-Options, CSP frame-ancestors: өөр сайт системийг iframe-д нууцаар ачаалж товч дарахыг залилахаас (clickjacking) хамгаална
 *  - X-Content-Type-Options: хөтөч файлын төрлийг таамаглаж (MIME sniffing) скрипт болгон ажиллуулахаас сэргийлнэ
 *  - Referrer-Policy: өөр сайт руу шилжихэд дотоод хуудасны бүтэн хаяг (хайлт, ID) дамжихгүй
 * Камерын эрхийг хязгаарлахгүй: «QR уншуулах» хуудас камер ашигладаг.
 */
class SecurityHeaders
{
    private const HEADERS = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'Content-Security-Policy' => "frame-ancestors 'self'",
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
