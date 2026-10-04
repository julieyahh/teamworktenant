<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestMessageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * php artisan test:test-message
     */
    protected $signature = 'test:test-message';

    /**
     * The console command description.
     */
    protected $description = 'Send a test message using PhilSMS API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $apiToken = '3054|DiwLMwEUsRhkE72BjGwtiFSs7LdeHzoh2evC0wOT';
        $apiUrl = 'https://app.philsms.com/api/v3/sms/send';

        // ⚠️ Replace this with your test number
        $recipient = '09103294272';
        $message = 'Hello world';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiToken,
            'Accept' => 'application/json',
        ])->post($apiUrl, [
            'recipient' => $recipient,
            'message' => $message,
        ]);

        if ($response->successful()) {
            $this->info('✅ Message sent successfully!');
            $this->line('Response: ' . $response->body());
        } else {
            $this->error('❌ Failed to send message.');
            $this->line('Response: ' . $response->body());
        }

        return Command::SUCCESS;
    }
}
