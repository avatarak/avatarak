import test from 'node:test';
import assert from 'node:assert/strict';
import { createHmac } from 'node:crypto';
import { verifyWebhook } from '../src/index.js';

const sign = (secret, t, body) => `t=${t},v1=${createHmac('sha256', secret).update(`${t}.${body}`).digest('hex')}`;

test('accepts a good signature and rejects tampering, wrong secret and stale timestamps', async () => {
    const body = '{"type":"ping"}', now = 1_700_000_000;
    const header = sign('whsec_x', now, body);
    assert.equal(await verifyWebhook(body, header, 'whsec_x', { now }), true);
    assert.equal(await verifyWebhook(body + ' ', header, 'whsec_x', { now }), false);
    assert.equal(await verifyWebhook(body, header, 'whsec_y', { now }), false);
    assert.equal(await verifyWebhook(body, header, 'whsec_x', { now: now + 301 }), false);
    assert.equal(await verifyWebhook(body, 'garbage', 'whsec_x', { now }), false);
    assert.equal(await verifyWebhook(body, null, 'whsec_x', { now }), false);
});
