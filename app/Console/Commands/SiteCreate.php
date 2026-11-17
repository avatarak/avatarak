<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Models\User;
use Illuminate\Console\Command;

class SiteCreate extends Command
{
    protected $signature = 'site:create {user : User id or email} {name} {domain}';

    protected $description = 'Create a connected site and print its API key (shown once)';

    public function handle(): int
    {
        $arg = $this->argument('user');
        $user = is_numeric($arg) ? User::find($arg) : User::where('email', $arg)->first();

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        [$site, $key] = Site::createWithKey([
            'user_id' => $user->id,
            'name' => $this->argument('name'),
            'domain' => $this->argument('domain'),
        ]);

        $this->info("Site #{$site->id} created. API key (store it now, it cannot be shown again):");
        $this->line($key);

        return self::SUCCESS;
    }
}
