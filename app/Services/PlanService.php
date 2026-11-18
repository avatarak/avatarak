<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/** Which plan a user is on right now, what it allows, and how entitlements are granted. */
class PlanService
{
    public static function plans(): array
    {
        return config('plans.plans');
    }

    public static function exists(string $plan): bool
    {
        return isset(self::plans()[$plan]);
    }

    /** The user's active subscription of the highest plan, or null (= Free). */
    public function active(User $user): ?Subscription
    {
        return $user->subscriptions()->where('starts_at', '<=', now())->where('ends_at', '>', now())->get()
            ->sortByDesc(fn ($s) => [self::plans()[$s->plan]['rank'] ?? 0, $s->ends_at->timestamp])->first();
    }

    public function planKey(User $user): string
    {
        $s = $this->active($user);

        return $s && self::exists($s->plan) ? $s->plan : 'free';
    }

    public function limit(User $user, string $name): int
    {
        return (int) (self::plans()[$this->planKey($user)]['limits'][$name] ?? 0);
    }

    public function can(User $user, string $feature): bool
    {
        return (bool) (self::plans()[$this->planKey($user)]['features'][$feature] ?? false);
    }

    /**
     * Add time on a plan. Buying the plan you already have extends it from the end of the current period;
     * a different plan starts now (no proration: simple and predictable).
     */
    public function grant(User $user, string $plan, int $days, string $source, ?int $paymentId = null): Subscription
    {
        abort_unless(self::exists($plan) && $plan !== 'free' && $days > 0, 422, 'Unknown plan.');

        $current = $user->subscriptions()->where('plan', $plan)->where('ends_at', '>', now())->orderByDesc('ends_at')->first();
        $start = $current ? $current->ends_at->copy() : now();

        return Subscription::create([
            'user_id' => $user->id, 'plan' => $plan, 'source' => $source, 'payment_id' => $paymentId,
            'starts_at' => $start, 'ends_at' => $start->copy()->addDays($days),
        ]);
    }

    public static function price(string $plan, string $period): int
    {
        return (int) (self::plans()[$plan]['price'][$period] ?? 0);
    }

    public static function days(string $period): int
    {
        return (int) (config('plans.periods')[$period] ?? 0);
    }
}
