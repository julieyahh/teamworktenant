<?php

/*
|--------------------------------------------------------------------------
| Public routes (no authentication)
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

// The landing page also hosts the "Apply for a unit" modal.
Route::get('/', [LandingController::class, 'index'])->name('landing');
