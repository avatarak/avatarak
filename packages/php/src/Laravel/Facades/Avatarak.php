<?php

namespace Avatarak\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string avatarUrl(string $emailOrHash, ?int $size = null, string $default = 'mp')
 * @method static ?\Avatarak\Profile profile(string $emailOrHash)
 * @method static array lookup(iterable $emailsOrHashes)
 * @method static string hash(string $emailOrHash)
 *
 * @see \Avatarak\Client
 */
class Avatarak extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Avatarak\Client::class;
    }
}
