<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Идэвхгүй болгосон хэрэглэгчийг дараагийн хүсэлтэд нь системээс гаргана.
 * Нэвтрэх үед is_active шалгагддаг ч аль хэдийн нээлттэй session болон «Намайг сана» cookie
 * (400 хоног) нь идэвхгүй болгосны дараа ч хүчинтэй хэвээр үлддэг байсан.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout(); // «Намайг сана» токеныг ч шинэчилж хүчингүй болгоно
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'Таны бүртгэл идэвхгүй болсон байна. Админд хандана уу.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 401)
                : redirect()->route('login')->withErrors(['login' => $message]);
        }

        return $next($request);
    }
}
