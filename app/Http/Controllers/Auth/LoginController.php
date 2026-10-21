public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    // is_active => true нь идэвхгүй болгосон хэрэглэгчийг нэвтрүүлэхгүй
    if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
        return back()
            ->withErrors(['email' => 'Имэйл, нууц үг буруу эсвэл бүртгэл идэвхгүй байна.'])
            ->onlyInput('email');
    }

    $request->session()->regenerate();

    return redirect()->intended(route('dashboard'));
}