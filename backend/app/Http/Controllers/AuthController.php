<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        abort_unless(Auth::attempt($credentials, true), 422, 'Invalid credentials.');
        $request->session()->regenerate();

        return $this->me($request);
    }

    public function me(Request $request)
    {
        return $request->user()->load('business.plan');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
