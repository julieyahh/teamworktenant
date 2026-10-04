<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class TenantPaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $tenant;

    /**
     * Create a new message instance.
     */
    public function __construct(User $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Define the envelope (sender, subject, headers, etc.)
     */
    public function envelope()
    {
        return new \Illuminate\Mail\Mailables\Envelope(
            subject: 'Monthly Payment Reminder',
        );
    }

    /**
     * Define the content of the email.
     */
    public function content()
    {
        return new \Illuminate\Mail\Mailables\Content(
            view: 'emails.payment_reminder',
            with: [
                'tenantName'     => $this->tenant->name,
                'rentBalance'    => $this->tenant->rent_balance,
                'utilityBalance' => $this->tenant->utility_balance,
                'status'         => $this->tenant->status,
            ]
        );
    }

}
