<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerifiedAccount extends Model
{
    protected $fillable = ['user_id', 'network', 'identifier', 'label', 'external_id', 'method', 'verified_at'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    /** Where the account lives, or null when we can only say who it is (LinkedIn gives no public profile address). */
    public function url(): ?string
    {
        return match ($this->network) {
            'telegram' => 'https://t.me/'.$this->identifier,
            'github' => 'https://github.com/'.$this->identifier,
            'linkedin' => null,
            default => 'https://'.$this->identifier,
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
