<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Site extends Model
{
    protected $fillable = [
        'user_id', 'name', 'domain', 'api_key_hash', 'api_key_prefix',
        'plan', 'scopes', 'white_label', 'rate_limit_per_minute', 'last_used_at', 'disabled_at',
    ];

    protected $hidden = ['api_key_hash'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'white_label' => 'boolean', 'last_used_at' => 'datetime', 'disabled_at' => 'datetime'];
    }

    /** Create a site; returns [Site, plainKey]. Plain key is never stored. */
    public static function createWithKey(array $attrs): array
    {
        $plain = 'avk_'.Str::random(40);
        $site = static::create($attrs + [
            'api_key_hash' => hash('sha256', $plain),
            'api_key_prefix' => substr($plain, 0, 8),
        ]);

        return [$site, $plain];
    }

    public static function findByKey(string $plain): ?self
    {
        return static::where('api_key_hash', hash('sha256', $plain))->first();
    }

    public const SCOPES = ['profiles:read', 'profiles:lookup'];

    /** A key without a stored list (all keys made before scopes existed) may do everything. */
    public function hasScope(string $scope): bool
    {
        return $this->scopes === null || in_array($scope, (array) $this->scopes, true);
    }

    /** Requests per minute: an administrator's override if there is one, else what the owner's plan allows. */
    public function effectiveRateLimit(): int
    {
        if ($this->rate_limit_per_minute !== null) {
            return max(1, (int) $this->rate_limit_per_minute);
        }

        return max(1, $this->user ? app(\App\Services\PlanService::class)->limit($this->user, 'rate_limit') : (int) config('plans.plans.free.limits.rate_limit'));
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null || $this->user?->isSuspended() === true;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
