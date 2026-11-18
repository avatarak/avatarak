<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Console\Command;

class AdminGrant extends Command
{
    protected $signature = 'admin:grant {email} {--revoke : Remove administrator access instead}';

    protected $description = 'Give (or with --revoke, take away) administrator access for an existing user';

    public function handle(): int
    {
        $user = User::where('email', UserEmail::normalize((string) $this->argument('email')))->first();
        if (! $user) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        $make = ! $this->option('revoke');
        if (! $make && User::where('is_admin', true)->count() <= 1 && $user->is_admin) {
            $this->error('This is the last administrator; create another one first.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => $make])->save();
        $this->info($user->email.($make ? ' is now an administrator.' : ' is no longer an administrator.'));

        return self::SUCCESS;
    }
}
