<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictEmployee
{
    /**
     * Ажилтан (удирдах эрхгүй хэрэглэгч) зөвхөн өөрийн хуудаснууд руу хандана.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->canManageComputers() && ! $request->is('/', 'profile', 'my/*', 'login', 'logout')) {
            return redirect('/');
        }

        return $next($request);
    }
}