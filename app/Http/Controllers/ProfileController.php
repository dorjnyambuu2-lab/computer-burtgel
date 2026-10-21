<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], [
            'name.required'  => 'Нэрээ оруулна уу.',
            'email.required' => 'Имэйлээ оруулна уу.',
            'email.email'    => 'Имэйл хаяг буруу байна.',
            'email.unique'   => 'Энэ имэйл өөр хэрэглэгч дээр бүртгэлтэй байна.',
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->save();

        return redirect()->route('profile.edit')->with('success', 'Профайл амжилттай хадгалагдлаа.');
    }
}