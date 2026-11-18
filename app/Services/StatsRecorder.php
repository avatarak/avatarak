<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Privacy-friendly counters. We store how many times a user's avatar/profile/export/API data was used
 * per day, and the HOST of the site that used it. Never an IP, a user agent or anything per visitor.
 * Recording can never break a response: every failure is swallowed.
 */
class StatsRecorder
{
    public const METRICS = ['profile', 'avatar', 'export', 'api'];

    private const BOT = '/bot|crawl|spider|slurp|preview|facebookexternalhit|embedly|monitor|uptime|curl|wget|python-requests|headless/i';

    public static function record(int $userId, string $metric, ?Request $request = null, ?string $host = null, int $by = 1): void
    {
        if (! config('stats.enabled') || ! in_array($metric, self::METRICS, true)) {
            return;
        }

        try {
            if ($request && ($request->isMethod('HEAD') || preg_match(self::BOT, (string) $request->userAgent()))) {
                return;
            }

            $date = now()->toDateString();
            self::bump('daily_stats', ['user_id' => $userId, 'date' => $date, 'metric' => $metric], $by);

            $host ??= $request ? self::refererHost($request) : null;
            if ($host) {
                $key = ['user_id' => $userId, 'date' => $date, 'metric' => $metric];
                $known = DB::table('stat_referrers')->where($key)->where('host', $host)->exists();
                if (! $known && DB::table('stat_referrers')->where($key)->count() >= (int) config('stats.max_hosts_per_day')) {
                    $host = 'other';
                }
                self::bump('stat_referrers', $key + ['host' => $host], $by);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** "https://www.Blog.example.com/post?x" -> "blog.example.com"; null for ours, junk or none. */
    public static function refererHost(Request $request): ?string
    {
        $host = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
        if (! is_string($host)) {
            return null;
        }
        $host = preg_replace('/^www\./', '', strtolower(rtrim($host, '.')));
        if (! preg_match('/^(?=.{1,100}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/', $host) || filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }
        $own = preg_replace('/^www\./', '', (string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($host === $own || $host === $request->getHost()) {
            return null;
        }

        return $host;
    }

    private static function bump(string $table, array $key, int $by): void
    {
        DB::table($table)->upsert(
            [$key + ['count' => $by]], array_keys($key), ['count' => DB::raw($table.'.count + '.(int) $by)],
        );
    }

    public static function prune(): int
    {
        $cut = now()->subDays((int) config('stats.retention_days'))->toDateString();

        return DB::table('daily_stats')->where('date', '<', $cut)->delete() + DB::table('stat_referrers')->where('date', '<', $cut)->delete();
    }

    /**
     * @return array{days:int,totals:array<string,int>,series:list<array>,referrers:list<array{host:string,count:int}>}
     */
    public function forUser(int $userId, int $days = 30): array
    {
        $days = max(1, min((int) config('stats.retention_days'), $days));
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = DB::table('daily_stats')->where('user_id', $userId)->where('date', '>=', $from->toDateString())->get();
        $byDate = [];
        $totals = array_fill_keys(self::METRICS, 0);
        foreach ($rows as $r) {
            $byDate[$r->date][$r->metric] = (int) $r->count;
            $totals[$r->metric] += (int) $r->count;
        }

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $series[] = ['date' => $d] + array_merge(array_fill_keys(self::METRICS, 0), $byDate[$d] ?? []);
        }

        $referrers = DB::table('stat_referrers')->where('user_id', $userId)->where('date', '>=', $from->toDateString())
            ->selectRaw('host, sum(count) as total')->groupBy('host')->orderByDesc('total')->limit(10)->get()
            ->map(fn ($r) => ['host' => $r->host, 'count' => (int) $r->total])->all();

        return ['days' => $days, 'totals' => $totals, 'series' => $series, 'referrers' => $referrers];
    }
}
