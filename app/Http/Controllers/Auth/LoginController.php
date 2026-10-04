<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function index()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required'
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            $user = Auth::user();

            // block tenants that are not approved
            if ($user->role === 'tenant' && $user->status !== 'approved') {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'Your account is still pending approval by the manager.',
                ]);
            }

            if ($user->role === 'manager') {
                return redirect()->route('manager.dashboard')
                                ->with('success', 'Login successful! Welcome back, ' . $user->name . '.');
            }

            if ($user->role === 'tenant') {
                return redirect()->route('tenant.home')
                                ->with('success', 'Login successful! Welcome back, ' . $user->name . '.');
            }

            return redirect('/');
        }

        return back()->withErrors([
            'email' => 'Invalid login credentials.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
                        ->with('success', 'Logout successfully!');
    }
}
