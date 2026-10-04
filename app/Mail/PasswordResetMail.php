<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $userName;
    public string $resetUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(string $userName, string $resetUrl)
    {
        $this->userName = $userName;
        $this->resetUrl = $resetUrl;
    }

    /**
     * Define the envelope.
     */
    public function envelope()
    {
        return new \Illuminate\Mail\Mailables\Envelope(
            subject: 'Reset Your Password',
        );
    }

    /**
     * Define the content.
     */
    public function content()
    {
        return new \Illuminate\Mail\Mailables\Content(
            view: 'emails.password_reset',
            with: [
                'userName' => $this->userName,
                'resetUrl' => $this->resetUrl,
            ]
        );
    }
}
