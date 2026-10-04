<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected string $apiToken;
    protected string $apiUrl;
    protected string $senderId;

    public function __construct()
    {
        $this->apiToken = env('PHILSMS_API_TOKEN');
        $this->apiUrl   = 'https://dashboard.philsms.com/api/v3/sms/send';
        $this->senderId = 'PhilSMS';
    }

    public function send(string $recipient, string $message): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Accept'        => 'application/json',
            ])->post($this->apiUrl, [
                'recipient' => $recipient,
                'message'   => $message,
                'sender_id' => $this->senderId,
            ]);

            if ($response->successful()) {
                Log::info("✅ SMS sent successfully to {$recipient}", $response->json());
                return true;
            } else {
                Log::error("❌ SMS failed for {$recipient}", $response->json());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("⚠️ SMS error for {$recipient}: {$e->getMessage()}");
            return false;
        }
    }
}
