<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/** Нэвтэрсэн хэрэглэгчийн «сүүлд харагдсан» цагийг шинэчилнэ (минутад нэгээс илүүгүй бичнэ) */
class TrackLastSeen
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Гарах хүсэлтийн дараа хэрэглэгч байхгүй тул энд дахин онлайн болгохгүй
        $user = $request->user();
        if ($user instanceof User && (! $user->is_online || ! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinute()))) {
            User::whereKey($user->id)->update(['last_seen_at' => now(), 'is_online' => true]);
        }

        return $response;
    }
}
