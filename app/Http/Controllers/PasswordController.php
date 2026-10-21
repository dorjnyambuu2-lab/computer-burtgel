<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function edit()
    {
        return view('my.password');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password',
                fn ($attr, $value, $fail) => hash_equals((string) config('security.superadmin_default_password'), (string) $value)
                    ? $fail('Анхны үндсэн нууц үгийг дахин ашиглах боломжгүй.') : null],
        ], [
            'current_password.current_password' => 'Одоогийн нууц үг буруу байна.',
            'password.confirmed' => 'Шинэ нууц үг таарахгүй байна.',
            'password.different' => 'Шинэ нууц үг хуучнаасаа өөр байх ёстой.',
        ]);

        $wasForced = $request->user()->must_change_password;

        $request->user()->update(['password' => Hash::make($data['password']), 'must_change_password' => false]);

        return $wasForced
            ? redirect()->route('dashboard')->with('success', 'Нууц үг амжилттай солигдлоо.')
            : redirect()->route('my.password.edit')->with('success', 'Нууц үг амжилттай солигдлоо.');
    }
}