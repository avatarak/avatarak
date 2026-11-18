<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** "Something important happened to your account": new device, password or two-step change, suspension… */
class SecurityNotice extends Mailable implements ShouldQueue
{
    use Queueable;

    public const KINDS = ['new_device', 'password_changed', 'two_factor_enabled', 'two_factor_disabled', 'account_suspended', 'account_unsuspended'];

    public function __construct(public string $kind, public array $context = [])
    {
        $this->locale(app()->getLocale());
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('app.notice_'.$this->kind.'_subject', ['app' => config('brand.name')]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.security-notice', with: [
            'kind' => $this->kind, 'context' => $this->context, 'securityUrl' => rtrim((string) config('app.url'), '/').'/panel',
        ]);
    }
}
