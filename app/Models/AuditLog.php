<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['admin_id', 'admin_label', 'action', 'target_type', 'target_id', 'target_label', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    /** Record what an administrator just did. $target is a model or null. */
    public static function record(string $action, ?Model $target = null, array $meta = [], ?User $admin = null): self
    {
        $admin ??= Auth::user();

        return static::create([
            'admin_id' => $admin?->id,
            'admin_label' => $admin ? ($admin->email) : 'system',
            'action' => $action,
            'target_type' => $target ? strtolower(class_basename($target)) : null,
            'target_id' => $target?->getKey(),
            'target_label' => $target ? self::label($target) : null,
            'meta' => $meta ?: null,
        ]);
    }

    private static function label(Model $m): string
    {
        return match (true) {
            $m instanceof User => $m->email,
            $m instanceof Profile => '@'.$m->username,
            $m instanceof Site => $m->domain,
            $m instanceof VerifiedAccount => $m->network.':'.$m->identifier,
            $m instanceof Report => '#'.$m->id,
            default => class_basename($m).'#'.$m->getKey(),
        };
    }
}
