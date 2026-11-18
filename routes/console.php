<?php

use App\Services\LoginCodeService;
use App\Services\StatsRecorder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('stats:prune', function () {
    $this->info('Deleted '.StatsRecorder::prune().' old statistics rows.');
})->purpose('Delete statistics older than STATS_RETENTION_DAYS');

Artisan::command('codes:prune', function (LoginCodeService $codes) {
    $this->info('Deleted '.$codes->prune().' expired sign-in codes and '.\App\Services\SecurityNotices::prune().' stale device records.');
})->purpose('Delete expired one-time sign-in codes');

Artisan::command('billing:remind', function () {
    $n = 0;
    \App\Models\Subscription::with('user')->whereNull('reminded_at')->where('ends_at', '>', now())->where('ends_at', '<=', now()->addDays((int) config('billing.remind_days')))
        ->get()->each(function ($sub) use (&$n) {
            // renewed already? then nothing to remind about
            $later = \App\Models\Subscription::where('user_id', $sub->user_id)->where('plan', $sub->plan)->where('ends_at', '>', $sub->ends_at)->exists();
            $sub->forceFill(['reminded_at' => now()])->save();
            if (! $later && $sub->user) {
                \App\Services\BillingNotices::send($sub->user, 'expiring', $sub);
                $n++;
            }
        });
    $this->info("Sent $n plan reminders.");
})->purpose('Email users whose paid plan ends soon');

Schedule::command('billing:remind')->dailyAt('09:15');
Schedule::command('backup:run')->dailyAt('02:30')->onOneServer()->withoutOverlapping();
Schedule::command('stats:prune')->dailyAt('03:10');
Schedule::command('codes:prune')->dailyAt('03:20');
