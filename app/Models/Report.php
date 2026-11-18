<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    public const REASONS = ['spam', 'impersonation', 'inappropriate', 'illegal', 'other'];

    protected $fillable = ['profile_id', 'reason', 'details', 'reporter_hash', 'status', 'handled_by', 'handled_at', 'note'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
