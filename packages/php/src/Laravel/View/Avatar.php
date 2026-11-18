<?php

namespace Avatarak\Laravel\View;

use Avatarak\Client;
use Illuminate\View\Component;

/** <x-avatarak-avatar email="a@b.ir" :size="64" default="identicon" /> renders an <img> (2x for sharp screens). */
class Avatar extends Component
{
    public string $src;

    public string $src2x;

    public function __construct(public string $email, public int $size = 64, public string $default = 'mp', ?Client $client = null)
    {
        $client ??= app(Client::class);
        $this->src = $client->avatarUrl($email, $size, $default);
        $this->src2x = $client->avatarUrl($email, min(512, $size * 2), $default);
    }

    public function render(): string
    {
        return <<<'BLADE'
<img src="{{ $src }}" srcset="{{ $src2x }} 2x" width="{{ $size }}" height="{{ $size }}" loading="lazy" {{ $attributes->merge(['alt' => '']) }}>
BLADE;
    }
}
