<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Системд нэвтрэх / гарах.
 *
 * Нэвтрэх дараалал:
 *  1. Нэвтрэх нэр (эсвэл имэйл) + нууц үгийг шалгана. Идэвхгүй бүртгэл нэвтэрч чадахгүй.
 *  2. Session-ийг шинэчилнэ (session fixation халдлагаас сэргийлнэ).
 *  3. Анхны үндсэн нууц үгээр (SUPERADMIN_DEFAULT_PASSWORD) нэвтэрсэн бол
 *     must_change_password = true болгоно.
 *  4. must_change_password = true бол «Нууц үг солих» хуудас руу шилжүүлнэ.
 *     ForcePasswordChange middleware нь нууц үгээ солих хүртэл өөр хуудас руу оруулахгүй.
 *     Супер админы олгосон түр нууц үгтэй хэрэглэгч ч мөн энэ замаар орно.
 */
class AuthController extends Controller
{
    /** Нэвтрэх хуудас */
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        // Хоёр талбар хоёулаа заавал бөглөгдсөн байх
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($request->input('login'));

        // Нэвтрэх нэрээр (superadmin, admin, employee) эсвэл имэйлээр нэвтэрнэ:
        // имэйлийн хэлбэртэй бол email баганаар, үгүй бол username баганаар хайна
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // Нууц үгийг hash-тай нь харьцуулж шалгана. is_active = true нөхцөлөөр
        // идэвхгүй болгосон бүртгэлийг нэвтрүүлэхгүй. "Намайг сана" сонгосон бол remember cookie үүснэ.
        $ok = Auth::attempt([
            $field => $login,
            'password' => $request->input('password'),
            'is_active' => true,
        ], $request->boolean('remember'));

        if (! $ok) {
            // Аль нь буруу байгааг (нэр үү, нууц үг үү) илчлэхгүй, ерөнхий алдаа буцаана.
            // Оруулсан нэвтрэх нэрийг үлдээж, нууц үгийг арилгана.
            return back()
                ->withErrors(['login' => 'Нэвтрэх нэр, нууц үг буруу эсвэл бүртгэл идэвхгүй байна.'])
                ->onlyInput('login');
        }

        // Нэвтэрсний дараа session ID-г шинээр үүсгэнэ (session fixation-оос хамгаална)
        $request->session()->regenerate();

        $user = $request->user();

        // Анхны үндсэн нууц үгээр нэвтэрсэн бол солих шаардлагатай гэж тэмдэглэнэ.
        // Seeder-ээр үүссэн Супер админ, эсвэл хэн нэгэн анхны нууц үгийг хэрэглэж байвал энд баригдана.
        // (Аль хэдийн тэмдэглэгдсэн бол дахин bcrypt шалгалт хийхгүй.)
        if (! $user->must_change_password && Hash::check((string) config('security.superadmin_default_password'), $user->password)) {
            $user->forceFill(['must_change_password' => true])->save();
        }

        // Анхны эсвэл түр нууц үгтэй бол хяналтын самбар руу биш, шууд нууц үг солих хуудас руу
        if ($user->must_change_password) {
            return redirect()->route('my.password.edit');
        }

        // Нэвтрэхээс өмнө орох гэж байсан хуудас руу, байхгүй бол хяналтын самбар руу
        return redirect()->intended(route('dashboard'));
    }

    /** Гарах: нэвтрэлтийг цуцалж, session болон CSRF токеныг устгана */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
