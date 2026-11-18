<?php

namespace App\Mail;

use App\Models\UserEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VerifyEmailAddress extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public UserEmail $email)
    {
        $this->locale(app()->getLocale());   // render in the language the user is using, even when queued
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('app.mail_subject', ['app' => config('brand.name')]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.verify',
            with: ['url' => rtrim((string) config('app.url'), '/').route('email.verify', $this->email->verification_token, false)],
        );
    }
}
