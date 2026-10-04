<?php

/*
|--------------------------------------------------------------------------
| Development / debug endpoints
|--------------------------------------------------------------------------
| Nothing in the app links to these. They are kept because /run-scheduler is
| sometimes used as a poor-man's cron on shared hosting.
|
| Before going live: delete this file and drop the require in routes/web.php.
| Anyone who knows the URLs can trigger them — none of them check auth.
*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

// Renders the alert component over AJAX.
Route::post('/alert/show', function (Request $request) {
    return view('components.alert', [
        'type'        => $request->type,
        'slot'        => $request->message,
        'dismissible' => true,
        'duration'    => 5000,
    ])->render();
})->name('alert.show');

// Fires the scheduler over HTTP, for hosts without real cron.
Route::get('/run-scheduler', function () {
    Artisan::call('schedule:run');

    return 'Scheduler executed successfully!';
});

// Confirms the mail credentials in .env actually work.
Route::get('/test-mail', function () {
    try {
        Mail::raw('Test email content', function ($message) {
            $message->to(config('mail.from.address'))->subject('Test Email');
        });

        return 'Email sent!';
    } catch (\Exception $e) {
        return $e->getMessage();
    }
});

// Rebuilds the config cache after editing .env.
Route::get('/clear-mail-cache', function () {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('config:cache');

    return 'MAIL SETTINGS REFRESHED';
});
