<?php

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
| The single source of truth for anything that runs on a timer.
| Loaded by routes/console.php (see the note there — it is not auto-loaded).
|
| Requires this cron entry on the server:
|   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
| Check what is registered with:  php artisan schedule:list
*/

use Illuminate\Support\Facades\Schedule;

// Flags tenants as pending / overdue / settled and emails them.
Schedule::command('update:tenant-status')
    ->dailyAt('00:00')
    ->appendOutputTo(storage_path('logs/tenant_status.log'));

// Raises each month's rent and assesses late penalties.
//
// Runs BEFORE leases:renew on purpose. A renewal changes the rate for the
// months that follow it; it does not bill one itself (see Lease::
// renewForAnotherYear). Charging first means a month that fell due today is
// charged at the rate that was in force today, not at tomorrow's new rate.
Schedule::command('rent:charge')
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/rent_charges.log'));

// Renews leases that hit their anniversary and applies the 5% rent increase.
Schedule::command('leases:renew')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/lease_renewals.log'));
