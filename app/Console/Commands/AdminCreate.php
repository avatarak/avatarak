<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminCreate extends Command
{
    protected $signature = 'admin:create {email : Email of the new administrator} {--name= : Display name} {--password= : Password (generated and printed when omitted)}';

    protected $description = 'Create an administrator account (or promote an existing user with admin:grant)';

    public function handle(): int
    {
        $email = UserEmail::normalize((string) $this->argument('email'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error("A user with $email already exists. Use: php artisan admin:grant $email");

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: Str::password(16, symbols: false));
        if (strlen($password) < 8) {
            $this->error('The password must be at least 8 characters.');

            return self::FAILURE;
        }

        $base = preg_replace('/[^a-z0-9_]/', '', strtolower(Str::before($email, '@'))) ?: 'admin';
        $base = 'a'.ltrim($base, '0123456789_');
        $base = substr(str_pad($base, 3, 'x'), 0, 24);
        $username = $base;
        for ($i = 2; Profile::where('username', $username)->exists() || in_array($username, \App\Rules\Username::RESERVED, true); $i++) {
            $username = $base.$i;
        }

        DB::transaction(function () use ($email, $password, $username) {
            $user = User::create(['name' => $this->option('name') ?: 'Administrator', 'email' => $email, 'password' => $password]);
            $user->forceFill(['is_admin' => true])->save();
            Profile::create(['user_id' => $user->id, 'username' => $username]);
            $user->emails()->create(['email' => $email, 'is_primary' => true, 'verified_at' => now()]);
        });

        $this->info("Administrator created: $email (username: $username)");
        if (! $this->option('password')) {
            $this->line("Password: $password");
            $this->warn('Store it now; it is not shown again. You can sign in with an email code too.');
        }

        return self::SUCCESS;
    }
}
