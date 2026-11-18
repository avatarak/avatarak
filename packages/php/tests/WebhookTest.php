<?php

namespace Avatarak\Tests;

use Avatarak\Webhook;
use PHPUnit\Framework\TestCase;

class WebhookTest extends TestCase
{
    public function test_verifies_and_rejects(): void
    {
        $body = '{"type":"ping"}';
        $now = 1_700_000_000;
        $h = "t={$now},v1=".hash_hmac('sha256', "{$now}.{$body}", 'whsec_x');
        $this->assertTrue(Webhook::verify($body, $h, 'whsec_x', 300, $now));
        $this->assertFalse(Webhook::verify($body.' ', $h, 'whsec_x', 300, $now));
        $this->assertFalse(Webhook::verify($body, $h, 'whsec_y', 300, $now));
        $this->assertFalse(Webhook::verify($body, $h, 'whsec_x', 300, $now + 301));
        $this->assertFalse(Webhook::verify($body, null, 'whsec_x'));
        $this->assertFalse(Webhook::verify($body, 'nonsense', 'whsec_x'));
    }
}
