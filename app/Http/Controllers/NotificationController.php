<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/** Систем доторх мэдэгдлүүд (бүх эрх) */
class NotificationController extends Controller
{
    /** Бүлэглэхэд авах сүүлийн мэдэгдлийн тоо (хуучныг нь харуулахгүй) */
    private const LIMIT = 1000;

    private const PER_PAGE = 20;

    /**
     * Ижил төрлийн, нэг хуудас руу заасан мэдэгдлүүдийг (жишээ нь нэг төхөөрөмжийн засварын явц) нэг мөр болгон
     * бүлэглэнэ. Бүлэг нь хамгийн сүүлийн мэдэгдлээрээ эрэмбэлэгдэнэ, бусад нь дэлгэхэд харагдана.
     */
    public function index(Request $request): View
    {
        $all = $request->user()->notifications()->limit(self::LIMIT)->get(['id', 'type', 'data', 'read_at', 'created_at']);

        $groups = $all->groupBy(fn ($n) => self::groupKey($n))
            ->map(fn ($items) => (object) [
                'latest' => $items->first(),
                'items' => $items,
                'count' => $items->count(),
                'unread' => $items->whereNull('read_at')->count(),
            ])->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $notifications = new LengthAwarePaginator($groups->forPage($page, self::PER_PAGE)->values(), $groups->count(), self::PER_PAGE, $page,
            ['path' => $request->url(), 'query' => $request->query()]);

        return view('notifications.index', [
            'groups' => $notifications,
            'truncated' => $all->count() >= self::LIMIT,
        ]);
    }

    /** Мэдэгдлийг (?all=1 бол бүлгийн бүх уншаагүйг) уншсан болгоод холбоос руу нь шилжинэ */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        if ($request->boolean('all')) {
            $key = self::groupKey($notification);
            $request->user()->unreadNotifications()->where('type', $notification->type)->get()
                ->filter(fn ($n) => self::groupKey($n) === $key)
                ->each->markAsRead();
        }

        $url = $notification->data['url'] ?? null;

        // Зөвхөн энэ системийн хаяг руу шилжүүлнэ
        return is_string($url) && str_starts_with($url, url('/'))
            ? redirect()->to($url)
            : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Бүх мэдэгдлийг уншсан болголоо.')->with('success_autohide', 3000);
    }

    /** Бүлгийн түлхүүр: мэдэгдлийн төрөл + заасан хуудас (холбоосгүй бол мэдэгдэл бүр тусдаа) */
    private static function groupKey(DatabaseNotification $notification): string
    {
        $url = $notification->data['url'] ?? null;

        return $notification->type.'|'.(is_string($url) && $url !== '' ? $url : $notification->id);
    }
}
