<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET хүсэлтийн хайлт, шүүлтүүрийн параметр (q, status, branch, ...) нь үргэлж текст байх ёстой.
 * Хаягийн мөрөнд гараар ?q[]=x гэж массив өгвөл controller-ууд (string) хөрвүүлэхдээ 500 алдаа өгдөг байсан тул
 * массив утгыг хасна (шүүлтүүр байхгүйтэй адил). Жинхэнэ массив параметрүүдийг (ALLOWED) хэвээр үлдээнэ.
 */
class ScalarQueryParams
{
    /** Массив байж болох query параметрүүд */
    private const ALLOWED = ['ids', 'computer_ids', 'categories', 'with_accessories'];

    public function handle(Request $request, Closure $next): Response
    {
        foreach ($request->query->all() as $key => $value) {
            if (is_array($value) && ! in_array($key, self::ALLOWED, true)) {
                $request->query->remove($key);
            }
        }

        return $next($request);
    }
}
