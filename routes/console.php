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
    $this->info('Deleted '.$codes->prune().' expired sign-in codes.');
})->purpose('Delete expired one-time sign-in codes');

Schedule::command('stats:prune')->dailyAt('03:10');
Schedule::command('codes:prune')->dailyAt('03:20');
