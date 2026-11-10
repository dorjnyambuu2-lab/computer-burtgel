<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Хариунд Content-Length тавина. Ингэснээр хөтөч хариугаа бүрэн авснаа шууд мэдэж, хуудсыг харуулна.
 * Үүнгүйгээр сервер хариу илгээсний дараах ажлыг (жишээ нь «deferred» имэйл) дуусгах хүртэл
 * хөтөч хүлээж, хуудас «гацсан» мэт харагддаг байсан.
 * Урсгал (Excel татах), файлын хариуд хэмжээ урьдчилан тодорхойгүй тул хамаарахгүй.
 */
class SetContentLength
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof StreamedResponse && ! $response instanceof BinaryFileResponse
            && ! $response->headers->has('Transfer-Encoding')) {
            $content = $response->getContent();
            if (is_string($content)) {
                $response->headers->set('Content-Length', (string) strlen($content));
            }
        }

        return $response;
    }
}
