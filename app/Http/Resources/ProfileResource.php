<?php

namespace App\Http\Resources;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public view of a profile. Never exposes emails, user id or privacy flags.
 *
 * @property Profile $resource
 */
class ProfileResource extends JsonResource
{
    public function __construct(Profile $profile, private string $hash)
    {
        parent::__construct($profile);
    }

    public function toArray(Request $request): array
    {
        $p = $this->resource;

        return [
            'hash' => $this->hash,
            'username' => $p->username,
            'profile_url' => route('profile.show', $p->username),
            'name_fa' => $p->name_fa,
            'name_en' => $p->name_en,
            'bio' => $p->bio,
            'website' => $p->website,
            'socials' => $p->socials ?: (object) [],
            'avatar_url' => ($p->show_avatar && $p->avatar_path) ? route('avatar', $this->hash) : null,
        ];
    }
}
