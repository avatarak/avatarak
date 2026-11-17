<?php

namespace App\Console\Commands;

use App\Models\UserEmail;
use Illuminate\Console\Command;

class EmailVerify extends Command
{
    protected $signature = 'email:verify {email : The address to mark as verified}';

    protected $description = 'Mark an email address as verified (useful locally, where no mail is sent)';

    public function handle(): int
    {
        $hash = UserEmail::hash($this->argument('email'));
        $claims = UserEmail::where('email_hash', $hash)->get();

        if ($claims->isEmpty()) {
            $this->error('No account has added that address.');

            return self::FAILURE;
        }
        if ($claims->whereNotNull('verified_at')->isNotEmpty()) {
            $this->info('Already verified.');

            return self::SUCCESS;
        }
        if ($claims->count() > 1) {
            $this->error('Several accounts claim this address; refusing to guess which one owns it.');

            return self::FAILURE;
        }

        $claims->first()->forceFill(['verified_at' => now(), 'verification_token' => null])->save();
        $this->info('Verified.');

        return self::SUCCESS;
    }
}
