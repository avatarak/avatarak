<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    public const LEVELS = ['info', 'success', 'warning'];

    protected $fillable = ['message_fa', 'message_en', 'level', 'active', 'dismissible', 'starts_at', 'ends_at', 'created_by'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'dismissible' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** Switched on and inside its time window. */
    public function scopeCurrent($query)
    {
        return $query->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function message(string $locale): string
    {
        return $locale === 'en' && $this->message_en ? $this->message_en : $this->message_fa;
    }
}
