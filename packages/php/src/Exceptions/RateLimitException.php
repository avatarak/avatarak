<?php

namespace Avatarak\Exceptions;

/** 429: this key is over its per-minute limit. Wait $retryAfter seconds. */
class RateLimitException extends ApiException
{
    public function __construct(string $message, int $status = 429, ?array $body = null, public readonly int $retryAfter = 60)
    {
        parent::__construct($message, $status, $body);
    }
}
