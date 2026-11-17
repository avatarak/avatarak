<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VerificationChallenge extends Model
{
    protected $fillable = ['user_id', 'network', 'identifier', 'token', 'expires_at', 'attempts'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function expired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** Create (or re-use a still-valid) challenge for this user + account. */
    public static function issue(User $user, string $network, string $identifier): self
    {
        $c = static::firstOrNew(['user_id' => $user->id, 'network' => $network, 'identifier' => $identifier]);
        if (! $c->exists || $c->expired()) {
            $c->token = Str::random(32);
            $c->attempts = 0;
            $c->expires_at = now()->addHours(config('verification.challenge_ttl_hours'));
            $c->save();
        }

        return $c;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
