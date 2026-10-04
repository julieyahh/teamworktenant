<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Lease;

class TenantApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $tenant;
    public Lease $lease;

    public function __construct(User $tenant, Lease $lease)
    {
        $this->tenant = $tenant;
        $this->lease = $lease;
    }

    public function envelope()
    {
        return new \Illuminate\Mail\Mailables\Envelope(
            subject: 'Tenant Application Approved',
        );
    }

    public function content()
    {
        return new \Illuminate\Mail\Mailables\Content(
            view: 'emails.tenant_approved',
            with: [
                'tenantName' => $this->tenant->name,
                'leaseTerm'  => $this->lease->lea_terms,
            ]
        );
    }
}
