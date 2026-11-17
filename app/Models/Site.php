<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Site extends Model
{
    protected $fillable = [
        'user_id', 'name', 'domain', 'api_key_hash', 'api_key_prefix',
        'plan', 'white_label', 'rate_limit_per_minute', 'last_used_at',
    ];

    protected $hidden = ['api_key_hash'];

    protected function casts(): array
    {
        return ['white_label' => 'boolean', 'last_used_at' => 'datetime'];
    }

    /** Create a site; returns [Site, plainKey]. Plain key is never stored. */
    public static function createWithKey(array $attrs): array
    {
        $plain = 'ira_' . Str::random(40);
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
