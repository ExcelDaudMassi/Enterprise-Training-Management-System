<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthController extends Controller
{
    /**
     * Tampilkan halaman login.
     * Jika sudah login, redirect langsung ke dashboard sesuai role.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user());
        }

        return Inertia::render('Auth/Login');
    }

    /**
     * Proses login: validasi kredensial, redirect sesuai role jika berhasil.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return $this->redirectByRole(Auth::user());
        }

        return back()->withErrors([
            'email' => 'Incorrect username or password.',
        ])->onlyInput('email');
    }

    /**
     * Logout dan kembali ke halaman login.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Redirect berdasarkan role user.
     */
    private function redirectByRole($user)
    {
        return match ($user->role) {
            'admin' => redirect()->route('admin.dashboard'),
            default => redirect()->route('user.dashboard'),
        };
    }

}

