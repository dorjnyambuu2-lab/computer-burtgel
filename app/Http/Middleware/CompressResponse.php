<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HTML, JSON хариуг gzip-ээр шахна (ихэвчлэн 4–6 дахин багасна).
 * Apache-ийн mod_deflate XAMPP-д унтраалттай тул шахалтыг програм талд хийнэ — алслагдсан салбараас
 * удаан сүлжээгээр хандахад хуудас хурдан ачаална. Хөтөч gzip дэмждэггүй, хариу жижиг (1 KB-аас бага),
 * урсгал (Excel), файл, аль хэдийн шахсан хариуд хамаарахгүй.
 *
 * SetContentLength-ээс ДОТОР ажиллах ёстой (bootstrap/app.php-д түүний дараа бүртгэнэ): эхлээд шахаад,
 * дараа нь шахсан хэмжээгээр Content-Length тавина.
 */
class CompressResponse
{
    private const MIN_BYTES = 1024;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! str_contains((string) $request->header('Accept-Encoding'), 'gzip')
            || $response instanceof StreamedResponse || $response instanceof BinaryFileResponse
            || $response->headers->has('Content-Encoding')
            || ! preg_match('~^(text/html|application/json|text/plain)~', (string) $response->headers->get('Content-Type'))) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || strlen($content) < self::MIN_BYTES) {
            return $response;
        }

        $gz = gzencode($content, 6);
        if ($gz === false) {
            return $response;
        }

        $response->setContent($gz);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Vary', trim($response->headers->get('Vary').', Accept-Encoding', ', '));

        return $response;
    }
}
