<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Анхны / түр нууц үгтэй хэрэглэгч нууц үгээ солихоос өмнө өөр хуудас руу орохгүй.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password && ! $request->is('my/password', 'logout')) {
            return redirect()->route('my.password.edit')
                ->with('warning', 'Та анхны эсвэл түр нууц үгээр нэвтэрсэн байна. Үргэлжлүүлэхийн өмнө шинэ нууц үг тохируулна уу.');
        }

        return $next($request);
    }
}
