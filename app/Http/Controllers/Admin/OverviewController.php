<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Avatar;
use App\Models\Profile;
use App\Models\Report;
use App\Models\Site;
use App\Models\User;
use App\Models\VerifiedAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OverviewController extends Controller
{
    public function show(): JsonResponse
    {
        $days = 30;
        $from = now()->subDays($days - 1)->startOfDay();

        $signups = DB::table('users')->where('created_at', '>=', $from)->selectRaw('date(created_at) as d, count(*) as c')->groupBy('d')->pluck('c', 'd');
        $uploads = DB::table('avatars')->where('created_at', '>=', $from)->selectRaw('date(created_at) as d, count(*) as c')->groupBy('d')->pluck('c', 'd');
        $traffic = DB::table('daily_stats')->where('date', '>=', $from->toDateString())->selectRaw('date, metric, sum(count) as c')->groupBy('date', 'metric')->get();
        $byDay = [];
        foreach ($traffic as $r) {
            $byDay[$r->date][$r->metric] = (int) $r->c;
        }

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $series[] = [
                'date' => $d, 'signups' => (int) ($signups[$d] ?? 0), 'uploads' => (int) ($uploads[$d] ?? 0),
                'avatar' => $byDay[$d]['avatar'] ?? 0, 'profile' => $byDay[$d]['profile'] ?? 0, 'export' => $byDay[$d]['export'] ?? 0, 'api' => $byDay[$d]['api'] ?? 0,
            ];
        }

        $sum = fn (string $k) => array_sum(array_column($series, $k));
        $prev = fn (string $table, string $col = 'created_at') => DB::table($table)->where($col, '>=', now()->subDays(2 * $days - 1)->startOfDay())->where($col, '<', $from)->count();

        $data = [
            'counts' => [
                'users' => User::count(), 'admins' => User::where('is_admin', true)->count(), 'suspended' => User::whereNotNull('suspended_at')->count(),
                'public_profiles' => Profile::visible()->count(), 'avatars' => Avatar::count(),
                'verified_accounts' => VerifiedAccount::count(),
                'verified_by_network' => VerifiedAccount::selectRaw('network, count(*) as c')->groupBy('network')->pluck('c', 'network'),
                'sites' => Site::count(), 'sites_active_24h' => Site::where('last_used_at', '>=', now()->subDay())->count(), 'sites_disabled' => Site::whereNotNull('disabled_at')->count(),
                'reports_open' => Report::where('status', 'open')->count(),
            ],
            'period' => [
                'days' => $days, 'signups' => $sum('signups'), 'signups_prev' => $prev('users'),
                'uploads' => $sum('uploads'), 'uploads_prev' => $prev('avatars'),
                'requests' => $sum('avatar') + $sum('profile') + $sum('export') + $sum('api'),
            ],
            'billing' => [
                'revenue_30d' => (int) DB::table('payments')->where('status', 'paid')->where('paid_at', '>=', $from)->sum('amount'),
                'revenue_prev' => (int) DB::table('payments')->where('status', 'paid')->where('paid_at', '>=', now()->subDays(2 * $days - 1)->startOfDay())->where('paid_at', '<', $from)->sum('amount'),
                'currency' => config('plans.currency'),
                'paying_users' => DB::table('subscriptions')->where('starts_at', '<=', now())->where('ends_at', '>', now())->distinct()->count('user_id'),
                'by_plan' => DB::table('subscriptions')->where('starts_at', '<=', now())->where('ends_at', '>', now())->selectRaw('plan, count(distinct user_id) as c')->groupBy('plan')->pluck('c', 'plan'),
                'gateway' => config('billing.gateway'),
            ],
            'series' => $series,
            'referrers' => DB::table('stat_referrers')->where('date', '>=', $from->toDateString())->selectRaw('host, sum(count) as total')->groupBy('host')->orderByDesc('total')->limit(8)->get()
                ->map(fn ($r) => ['host' => $r->host, 'count' => (int) $r->total])->all(),
            'recent_users' => User::with('profile')->latest('id')->limit(8)->get()->map(fn (User $u) => UserController::brief($u))->all(),
            'open_reports' => Report::with('profile')->where('status', 'open')->latest('id')->limit(6)->get()->map(fn (Report $r) => ReportController::present($r))->all(),
            'system' => [
                'php' => PHP_VERSION, 'laravel' => app()->version(), 'env' => app()->environment(), 'debug' => (bool) config('app.debug'),
                'mail' => config('mail.default'), 'queue' => config('queue.default'), 'cache' => config('cache.default'), 'db' => config('database.default'),
                'telegram' => filled(config('verification.telegram.bot_token')), 'github_oauth' => filled(config('verification.github.client_id')),
                'stats' => (bool) config('stats.enabled'),
            ],
        ];

        return response()->json($data)->header('Cache-Control', 'no-store');
    }
}
