<?php

namespace Avatarak;

/** Verify a webhook delivery: Webhook::verify($rawBody, $_SERVER['HTTP_AVATARAK_SIGNATURE'], $secret). */
final class Webhook
{
    /** @param int $tolerance seconds a signature stays valid (replay protection) */
    public static function verify(string $rawBody, ?string $header, string $secret, int $tolerance = 300, ?int $now = null): bool
    {
        if (! $header || ! preg_match('/^t=(\d+),v1=([a-f0-9]{64})$/', $header, $m)) {
            return false;
        }
        if (abs(($now ?? time()) - (int) $m[1]) > $tolerance) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $m[1].'.'.$rawBody, $secret), $m[2]);
    }
}
