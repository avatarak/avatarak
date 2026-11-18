<?php

namespace App\Mail;

use App\Services\LoginCodeService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LoginCode extends Mailable
{
    public function __construct(public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('app.code_subject', ['code' => $this->code, 'app' => config('brand.name')]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.login-code', with: ['code' => $this->code, 'minutes' => LoginCodeService::TTL_MINUTES]);
    }
}
