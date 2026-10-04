<?php

/*
|--------------------------------------------------------------------------
| Authentication routes
|--------------------------------------------------------------------------
| Login / logout / registration / password reset.
*/

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use Illuminate\Support\Facades\Route;

// --- Sign in / out ---------------------------------------------------------
Route::get('/login',   [LoginController::class, 'index'])->name('login');
Route::post('/login',  [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// --- Registration ----------------------------------------------------------
Route::get('/register',              [RegisterController::class, 'index'])->name('register');
Route::post('/register',             [RegisterController::class, 'register'])->name('register.submit');
Route::post('/register/check-email', [RegisterController::class, 'checkEmail'])->name('register.check-email');

// --- Forgot password (request the emailed link) ----------------------------
Route::get('/forgot-password', [ForgotPasswordController::class, 'showForgotForm'])->name('password.request');
Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');

// --- Reset password (arriving from the emailed link) -----------------------
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password',        [ResetPasswordController::class, 'reset'])->name('password.update');
