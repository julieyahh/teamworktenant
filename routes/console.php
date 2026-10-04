<?php

use App\Mail\WelcomeMail;
use App\Mail\TenantVoidedMail;
use App\Mail\TenantOverdueMail;
use App\Models\Payment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| Here is where you can define all of your Closure based console commands.
| Each Closure is bound to a command instance allowing a simple approach
| to interacting with each command's IO methods.
|
*/

// Example default command
Artisan::command('inspire', function () {
    $this->comment(\Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| Every schedule lives in routes/schedule.php — one file, one place to look.
|
| IMPORTANT: routes/schedule.php is NOT loaded automatically by Laravel 12.
| Only this file is (via `commands:` in bootstrap/app.php), so schedule.php has
| to be required from here. Before this line existed, everything declared in
| schedule.php — including the `leases:renew` 5% rent increase — never ran.
*/
require __DIR__ . '/schedule.php';

// ✅ Send Welcome Mail
Artisan::command('send-welcome-mail', function () {
    $recipientEmail = 'jalix2003@gmail.com';
    $recipientName  = 'Yuehan';

    Mail::to($recipientEmail)->send(new WelcomeMail($recipientName, $recipientEmail));

    $this->info("✅ Welcome email sent to {$recipientEmail}");
})->purpose('Send welcome mail');

// ✅ Test Tenant Status Command (using actual statuses)
Artisan::command('test-tenant-status', function () {
    $this->info("🚀 Starting tenant status test based on payment statuses...");

    $today = Carbon::now();

    $tenants = User::where('role', 'tenant')->get();

    foreach ($tenants as $tenant) {
        // Skip if no email
        if (!$tenant->email) continue;

        // Check deposit for voided accounts
        $hasPaidDeposit = Payment::where('tenant_id', $tenant->id)
            ->where('payment_for', 'Deposit')
            ->exists();

        if (!$hasPaidDeposit && $tenant->created_at->diffInDays($today) >= 7) {
            try {
                Mail::to($tenant->email)->send(new TenantVoidedMail($tenant));
                $this->info("📩 TenantVoidedMail sent to {$tenant->email}");
            } catch (\Exception $e) {
                $this->error("❌ Failed to send void email to {$tenant->email}: " . $e->getMessage());
            }
            continue;
        }

        // Check for overdue payments
        if ($tenant->rental_payment_status === 'overdue' || $tenant->utility_payment_status === 'overdue') {
            try {
                Mail::to($tenant->email)->send(new TenantOverdueMail($tenant));
                $this->info("📩 TenantOverdueMail sent to {$tenant->email}");
            } catch (\Exception $e) {
                $this->error("❌ Failed to send overdue email to {$tenant->email}: " . $e->getMessage());
            }
        }
    }

    $this->info("🎯 Test complete. Check your configured mailer for emails.");
})->purpose('Test tenant status updates and email notifications based on payment statuses');

// text message test
Artisan::command('test:test-message', function () {
    $apiToken = '3054|DiwLMwEUsRhkE72BjGwtiFSs7LdeHzoh2evC0wOT';
    $apiUrl = 'https://app.philsms.com/api/v3/sms/send';

    // ✅ Use correct format with +63 country code
    $recipient = '+639659748827';
    $message = 'TenantMS API test';

    $this->line('--------------------------------------');
    $this->info('console.log("Sending SMS...");');
    $this->line('--------------------------------------');

    try {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiToken,
            'Accept' => 'application/json',
        ])->post($apiUrl, [
            'recipient' => $recipient,
            'message' => $message,
            'sender_id' => 'PhilSMS'
        ]);

        if ($response->successful()) {
            $this->info('console.log("✅ Message sent successfully!");');
            $this->line('console.log(' . json_encode($response->json(), JSON_PRETTY_PRINT) . ');');
        } else {
            $this->error('console.log("❌ Failed to send message");');
            $this->line('console.log(' . json_encode($response->json(), JSON_PRETTY_PRINT) . ');');
        }
    } catch (\Exception $e) {
        $this->error('console.log("⚠️ Error occurred: ' . addslashes($e->getMessage()) . '");');
    }

    $this->line('--------------------------------------');
})->purpose('Send a test message using PhilSMS API');
