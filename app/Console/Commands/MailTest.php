<?php

namespace App\Console\Commands;

use App\Mail\LoginCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MailTest extends Command
{
    protected $signature = 'mail:test {email : Where to send the test message} {--locale=fa : fa or en}';

    protected $description = 'Send a test email right now (not queued) to check your SMTP settings';

    public function handle(): int
    {
        $to = (string) $this->argument('email');
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }
        app()->setLocale($this->option('locale') === 'en' ? 'en' : 'fa');

        $this->line('Mailer: '.config('mail.default').' · from: '.config('mail.from.address'));
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            $this->warn('MAIL_MAILER is "'.config('mail.default').'": the message is only written to the log, not delivered. Set MAIL_MAILER=smtp.');
        }

        try {
            Mail::to($to)->sendNow(new LoginCode('123456'));
        } catch (\Throwable $e) {
            $this->error('Sending failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Test message sent to $to.");

        return self::SUCCESS;
    }
}
