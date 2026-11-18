<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryImage extends Model
{
    protected $fillable = ['user_id', 'path', 'caption', 'caption_alt', 'position', 'width', 'height'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function thumbPath(): string
    {
        return $this->path.'.thumb.jpg';
    }
}
