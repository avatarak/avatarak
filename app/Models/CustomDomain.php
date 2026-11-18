<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class CustomDomain extends Model
{
    protected $fillable = ['user_id', 'host', 'token', 'verified_at', 'last_checked_at', 'checks'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'last_checked_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        $forget = fn (self $d) => Cache::forget(self::cacheKey($d->host));
        static::saved($forget);
        static::deleted($forget);
    }

    public static function cacheKey(string $host): string
    {
        return 'cdomain:'.$host;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
