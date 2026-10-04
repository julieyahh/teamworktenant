<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class TenantOverdueMail extends Mailable
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
            subject: 'Payment Overdue Notice',
        );
    }

    public function content()
    {
        return new \Illuminate\Mail\Mailables\Content(
            view: 'emails.tenant_overdue',
            with: [
                'tenantName'   => $this->tenant->name,
                'rentBalance'  => $this->tenant->rent_balance,
                'utilityBalance' => $this->tenant->utility_balance,
                'status'       => $this->tenant->rental_payment_status,
            ]
        );
    }
}
