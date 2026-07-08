<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $token,
        public Company $company,
        public bool $isExistingUser,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('app.name', 'Facturex') . ' - Invitation à rejoindre la plateforme',
        );
    }

    public function content(): Content
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        return new Content(
            view: 'emails.invitation',
            with: [
                'companyName' => $this->company->company_name,
                'email' => $this->email,
                'token' => $this->token,
                'acceptUrl' => $frontendUrl . '/accept-invitation?token=' . $this->token,
                'isExistingUser' => $this->isExistingUser,
                'appName' => config('app.name', 'Facturex'),
                'fromEmail' => config('mail.from.address', 'benaissamorad559@gmail.com'),
            ],
        );
    }

}