<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\User;
use App\Mail\PasswordResetMail;

class ForgotPasswordController extends Controller
{
    /**
     * Show the forgot password form
     */
    public function showForgotForm()
    {
        return view('auth.forgot_password'); // Ensure this Blade exists
    }

    /**
     * Handle sending password reset link email
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return back()->withErrors(['email' => 'We can’t find a user with that email address.']);
        }

        // Generate token
        $token = Str::random(64);
        $user->update([
            'reset_token' => $token,
            'reset_token_created_at' => now()
        ]);

        $resetUrl = url("/reset-password/{$token}");

        try {
            Mail::to($user->email)->send(new PasswordResetMail($user->name, $resetUrl));
        } catch (\Exception $e) {
            Log::error("Failed to send reset email: {$e->getMessage()}");
            return back()->withErrors(['email' => 'Failed to send password reset email.']);
        }

        return back()->with('success', 'We’ve sent you a password reset link!');
    }

    /**
     * Show the password reset form
     */
    public function showResetForm($token)
    {
        $user = User::where('reset_token', $token)->first();

        if (!$user) {
            return redirect()->route('password.request')
                ->withErrors(['token' => 'This password reset link is invalid.']);
        }

        // Check if token expired (1 hour expiration)
        if ($user->reset_token_created_at->diffInMinutes(now()) > 60) {
            return redirect()->route('password.request')
                ->withErrors(['token' => 'This password reset link has expired.']);
        }

        return view('auth.reset_password', ['token' => $token, 'email' => $user->email]);
    }

    /**
     * Handle resetting the password
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed', // expects password_confirmation field
            ],
        ]);

        $user = User::where('reset_token', $request->token)->first();

        if (!$user) {
            return redirect()->route('password.request')
                ->withErrors(['token' => 'Invalid password reset token.']);
        }

        // Check token expiration
        if ($user->reset_token_created_at->diffInMinutes(now()) > 60) {
            return redirect()->route('password.request')
                ->withErrors(['token' => 'This password reset link has expired.']);
        }

        // Update password and clear token
        $user->update([
            'password' => Hash::make($request->password),
            'reset_token' => null,
            'reset_token_created_at' => null,
        ]);

        return redirect()->route('login')->with('success', 'Your password has been reset successfully!');
    }
}
