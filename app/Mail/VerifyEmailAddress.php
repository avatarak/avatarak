<?php

namespace App\Mail;

use App\Models\UserEmail;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VerifyEmailAddress extends Mailable
{
    public function __construct(public UserEmail $email) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('app.mail_subject', ['app' => config('brand.name')]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.verify',
            with: ['url' => route('email.verify', $this->email->verification_token)],
        );
    }
}
