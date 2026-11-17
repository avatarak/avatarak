<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserEmail extends Model
{
    protected $fillable = [
        'user_id', 'email', 'email_hash', 'verification_token',
        'verified_at', 'is_primary', 'is_public',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'is_primary' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public static function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }

    public static function hash(string $email): string
    {
        return hash('sha256', self::normalize($email));
    }

    protected static function booted(): void
    {
        static::saving(function (self $m) {
            $m->email = self::normalize($m->email);
            $m->email_hash = self::hash($m->email);
        });
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
