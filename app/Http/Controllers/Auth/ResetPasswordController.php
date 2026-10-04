<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\User;

class ResetPasswordController extends Controller
{
    // Show reset form
    public function showResetForm($token)
    {
        return view('auth.reset_password', ['token' => $token]);
    }

    // Handle form submission
    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|confirmed|min:8',
            'token' => 'required|string',
        ]);

        $user = User::where('email', $request->email)
                    ->where('reset_token', $request->token)
                    ->first();

        if (!$user) {
            return back()->withErrors(['email' => 'Invalid token or email.']);
        }

        // Ensure reset_token_created_at is a Carbon instance
        if (!$user->reset_token_created_at || Carbon::parse($user->reset_token_created_at)->addMinutes(60)->isPast()) {
            return back()->withErrors(['email' => 'Token expired.']);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->reset_token = null;
        $user->reset_token_created_at = null;
        $user->save();

        return redirect()->route('login')->with('success', 'Password reset successfully!');
    }
}
