<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    public const EVENTS = ['profile.updated', 'avatar.changed', 'verification.added', 'verification.removed'];
    public const MAX_FAILURES = 20;

    protected $fillable = ['user_id', 'url', 'secret', 'events', 'active', 'failures', 'last_delivery_at', 'last_status', 'disabled_at'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'events' => 'array', 'active' => 'boolean', 'last_delivery_at' => 'datetime', 'disabled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function wants(string $event): bool
    {
        return $this->active && $this->disabled_at === null && in_array($event, (array) $this->events, true);
    }
}
