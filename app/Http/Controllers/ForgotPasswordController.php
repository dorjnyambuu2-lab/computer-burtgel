<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Notifications\PasswordResetRequested;
use App\Support\Notify;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

/**
 * Нууц үг мартсан үед:
 *  - Супер админ: бүртгэлтэй имэйл рүү нь нэг удаагийн холбоос очно. Холбоосоор орж
 *    сэргээх түлхүүрээ (SUPERADMIN_RECOVERY_KEY) оруулсны дараа шинэ нууц үг тохируулна.
 *  - Админ: имэйлтэй бол сэргээх холбоос имэйлээр очно (түлхүүргүй). Имэйлгүй эсвэл илгээж чадаагүй бол
 *    Супер админ руу хүсэлт очно, Супер админ түр нууц үг олгоно.
 *  - Ажилтан: өөрийн салбарын Админ болон Супер админ руу хүсэлт очно, аль нэг нь түр нууц үг олгоно.
 */
class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['login' => ['required', 'string', 'max:255']], [
            'login.required' => 'Нэвтрэх нэр эсвэл имэйлээ оруулна уу.',
        ]);

        $login = trim($request->input('login'));
        $user = User::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('username', $login)->orWhere('email', $login))
            ->first();

        if ($user?->isSuperadmin() && filled($user->email)) {
            $this->sendResetLink($user);
        } elseif ($user?->isSuperadmin()) {
            // Имэйлгүй (хуучин) супер админ: холбоос илгээх боломжгүй — бусад супер админд хүсэлт очно
            // (өмнө нь холбоос илгээх гэж өгөгдлийн санд алдаа өгөөд, юу ч хийгддэггүй байсан)
            $this->requestReset($user, $request);
        } elseif ($user) {
            // Имэйлтэй админ холбоогоор өөрөө сэргээнэ; бусад нь хүсэлтээр түр нууц үг авна
            $linked = $user->isAdmin() && filled($user->email) && $this->sendResetLink($user);
            if (! $linked) {
                $this->requestReset($user, $request);
            }
        }

        // Бүртгэл байгаа эсэх, ямар эрхтэйг илчлэхгүйн тулд ижил хариу өгнө
        return back()->with('status',
            'Хүсэлт хүлээн авлаа. Супер админ болон имэйлтэй админд сэргээх холбоос имэйлээр очно. '
            .'Бусад тохиолдолд хүсэлт Супер админ эсвэл салбарын Админ руу илгээгдсэн тул түр нууц үгийг тэднээс авна уу.');
    }

    /** Сэргээх холбоос илгээнэ. Илгээсэн (эсвэл саяхан илгээсэн) бол true, чадаагүй бол false */
    private function sendResetLink(User $user): bool
    {
        try {
            return in_array(Password::broker()->sendResetLink(['email' => $user->email]), [Password::RESET_LINK_SENT, Password::RESET_THROTTLED], true);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Нууц үг сэргээх хүсэлт үүсгэж, шийдвэрлэх эрхтэй хүмүүст мэдэгдэнэ:
     *  - Ажилтан: өөрийн салбарын Админ + Супер админ (аль нэг нь түр нууц үг олгоно; салбаргүй бол Супер админ)
     *  - Админ: зөвхөн Супер админ
     * Хэн шийдвэрлэж болохыг PasswordResetRequest::scopeVisibleTo тодорхойлно.
     */
    private function requestReset(User $user, Request $request): void
    {
        // Давтан дарвал шинэ хүсэлт үүсгэхгүй
        if (PasswordResetRequest::where('user_id', $user->id)->where('status', 'pending')->exists()) {
            return;
        }

        $resetRequest = PasswordResetRequest::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'ip_address' => $request->ip(),
        ]);

        // Ажилтных — өөрийн салбарын Админ болон Супер админ; админых — зөвхөн Супер админ
        $recipients = User::activeSuperadmins();
        if ($user->role === 'employee' && $user->branch_id) {
            $recipients = $recipients->merge(
                User::query()->where('role', 'admin')->where('is_active', true)->where('branch_id', $user->branch_id)->get()
            );
        }

        Notify::send($recipients, new PasswordResetRequested($resetRequest));
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email'),
            // Сэргээх түлхүүр зөвхөн Супер админд
            'needsRecoveryKey' => filled(config('security.superadmin_recovery_key'))
                && ! User::where('email', (string) $request->query('email'))->first()?->isAdmin(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = User::where('email', (string) $request->input('email'))->first();
        // Сэргээх түлхүүр зөвхөн Супер админд (Админ зөвхөн имэйлийн холбоогоор)
        $recoveryKey = $user?->isAdmin() ? null : config('security.superadmin_recovery_key');

        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
            'recovery_key' => [filled($recoveryKey) ? 'required' : 'nullable', 'string'],
        ], [
            'password.confirmed' => 'Шинэ нууц үг таарахгүй байна.',
            'password.min' => 'Нууц үг хамгийн багадаа 8 тэмдэгт байна.',
            'recovery_key.required' => 'Сэргээх түлхүүрээ оруулна уу.',
        ]);

        // Аюулгүй байдлын шалгалт: зөвхөн Супер админ, идэвхтэй Админ; сэргээх түлхүүр зөв байх
        $keyOk = blank($recoveryKey) || hash_equals((string) $recoveryKey, (string) $request->input('recovery_key'));
        $allowed = $user?->isSuperadmin() || ($user?->isAdmin() && $user->is_active);

        if (! $allowed || ! $keyOk) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Сэргээх холбоос эсвэл түлхүүр буруу байна.']);
        }

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Сэргээх холбоос хүчингүй эсвэл хугацаа нь дууссан байна. Дахин хүсэлт илгээнэ үү.']);
        }

        // Хүлээгдэж буй түр нууц үгийн хүсэлт хэрэггүй болно
        PasswordResetRequest::where('user_id', $user->id)->where('status', 'pending')
            ->update(['status' => 'resolved', 'resolved_at' => now()]);

        return redirect()->route('login')->with('status', 'Нууц үг шинэчлэгдлээ. Шинэ нууц үгээрээ нэвтэрнэ үү.');
    }
}
