<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BillingNotice extends Mailable implements ShouldQueue
{
    use Queueable;

    public const KINDS = ['receipt', 'expiring', 'granted'];

    public function __construct(public string $kind, public array $data = [])
    {
        $this->locale(app()->getLocale());
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('app.billing_'.$this->kind.'_subject', ['app' => config('brand.name'), 'plan' => $this->data['plan_name'] ?? '']));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.billing-notice', with: ['kind' => $this->kind, 'd' => $this->data]);
    }
}
