<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class TenantDepositReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $tenant;

    public function __construct(User $tenant)
    {
        $this->tenant = $tenant;
    }

    public function envelope()
    {
        return new \Illuminate\Mail\Mailables\Envelope(
            subject: 'Deposit Payment Reminder',
        );
    }

    public function content()
    {
        return new \Illuminate\Mail\Mailables\Content(
            view: 'emails.deposit_reminder',
            with: [
                'tenantName'     => $this->tenant->name,
                'rentBalance'    => $this->tenant->rent_balance,
                'utilityBalance' => $this->tenant->utility_balance,
                'status'         => $this->tenant->status,
            ]
        );
    }
}
