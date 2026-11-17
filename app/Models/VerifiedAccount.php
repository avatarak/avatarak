<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerifiedAccount extends Model
{
    protected $fillable = ['user_id', 'network', 'identifier', 'external_id', 'method', 'verified_at'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function url(): string
    {
        return match ($this->network) {
            'telegram' => 'https://t.me/'.$this->identifier,
            default => 'https://'.$this->identifier,
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
