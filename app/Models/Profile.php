<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    protected $fillable = [
        'user_id', 'username', 'name_fa', 'name_en', 'bio', 'website', 'socials',
        'avatar_path', 'avatar_version', 'show_avatar', 'show_profile', 'allow_indexing', 'accent_color', 'section_order',
    ];

    protected function casts(): array
    {
        return [
            'socials' => 'array',
            'section_order' => 'array',
            'show_avatar' => 'boolean',
            'show_profile' => 'boolean',
            'allow_indexing' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
