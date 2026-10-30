<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Нууц үг сэргээх хүсэлтүүд: Супер админ — админ, ажилтных; Админ — өөрийн салбарын ажилтных
 * (PasswordResetRequest::scopeVisibleTo)
 */
class PasswordResetRequestController extends Controller
{
    public function index(Request $request): View
    {
        $requests = PasswordResetRequest::with(['user', 'resolver'])
            ->visibleTo($request->user())
            ->when(array_key_exists((string) $request->query('status'), PasswordResetRequest::STATUSES),
                fn ($q) => $q->where('status', $request->query('status')))
            ->orderByRaw("status = 'pending' desc")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // Төлвийн табуудын тоо
        $statusCounts = PasswordResetRequest::visibleTo($request->user())
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->all();

        return view('password-requests.index', compact('requests', 'statusCounts'));
    }

    /** Түр нууц үг олгоно. Хэрэглэгч түүгээр нэвтэрмэгц шинэ нууц үг солих шаардлагатай. */
    public function resolve(Request $request, PasswordResetRequest $passwordRequest): RedirectResponse
    {
        $this->authorizeRequest($request, $passwordRequest);
        abort_unless($passwordRequest->status === 'pending', 409, 'Энэ хүсэлт аль хэдийн шийдвэрлэгдсэн.');

        $user = $passwordRequest->user;
        $temporary = Str::password(12, symbols: false);

        $user->forceFill([
            'password' => $temporary,
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ])->save();

        // Энэ болон нэг хэрэглэгчийн бусад хүлээгдэж буй хүсэлтийг хамт хаана
        PasswordResetRequest::where('user_id', $user->id)->where('status', 'pending')
            ->update(['status' => 'resolved', 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);

        return back()
            // Ногоон мэдэгдэл 3 секундэд алга болно; түр нууц үгийн шар хайрцаг хэвээр (хуулж авах)
            ->with('success', $user->name.'-д түр нууц үг олголоо. Нэвтэрмэгц шинэ нууц үг солино.')
            ->with('success_autohide', 3000)
            ->with('temporary_password', ['user' => $user->name.' ('.$user->username.')', 'password' => $temporary]);
    }

    public function reject(Request $request, PasswordResetRequest $passwordRequest): RedirectResponse
    {
        $this->authorizeRequest($request, $passwordRequest);
        abort_unless($passwordRequest->status === 'pending', 409, 'Энэ хүсэлт аль хэдийн шийдвэрлэгдсэн.');

        $passwordRequest->update(['status' => 'rejected', 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);

        return back()->with('success', 'Хүсэлтийг татгалзлаа.')->with('success_autohide', 3000);
    }

    /** Админ зөвхөн өөрийн салбарын ажилтны хүсэлтийг шийдвэрлэнэ */
    private function authorizeRequest(Request $request, PasswordResetRequest $passwordRequest): void
    {
        abort_unless(PasswordResetRequest::visibleTo($request->user())->whereKey($passwordRequest->id)->exists(), 403);
    }
}
