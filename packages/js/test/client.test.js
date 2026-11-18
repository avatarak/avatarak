import test from 'node:test';
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { Avatarak, ApiError, AuthenticationError, RateLimitError, avatarUrl, avatarUrlFromHash, hashEmail, MAX_BATCH } from '../src/index.js';

const sha = (s) => createHash('sha256').update(s).digest('hex');
const profile = (hash, over = {}) => ({ hash, username: 'ali', profile_url: 'https://x/ali', name_fa: null, name_en: 'Ali', headline: null, location: null, bio: null, website: null, socials: {}, links: [], verified_accounts: [], avatar_url: null, ...over });

function fakeFetch(handler) {
    const calls = [];
    const f = async (url, init) => {
        calls.push({ url, init });
        const { status = 200, body = {}, headers = {} } = await handler(url, init, calls.length);
        return { ok: status >= 200 && status < 300, status, headers: new Headers(headers), json: async () => (typeof body === 'string' ? JSON.parse(body) : body) };
    };
    f.calls = calls;
    return f;
}

test('hashEmail normalises like the server and passes hashes through', async () => {
    assert.equal(await hashEmail('  Ali@Example.COM '), sha('ali@example.com'));
    assert.equal(await hashEmail(sha('a').toUpperCase()), sha('a'));
    assert.equal(await hashEmail('MyEmailAddress@example.com '), '84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee');
});

test('avatarUrl: no key, no request, optional size and default', async () => {
    const h = sha('ali@example.com');
    assert.equal(await avatarUrl('ali@example.com'), `https://avatarak.ir/avatar/${h}`);
    assert.equal(await avatarUrl('ALI@example.com', { size: 96, default: 'identicon' }), `https://avatarak.ir/avatar/${h}?s=96&d=identicon`);
    assert.equal(await avatarUrl(h, { size: 48, default: 'mp', baseUrl: 'https://my.host/' }), `https://my.host/avatar/${h}?s=48`);
    assert.equal(avatarUrlFromHash(h.toUpperCase()), `https://avatarak.ir/avatar/${h}`);
});

test('profile sends only the hash and the key, returns the data object', async () => {
    const h = sha('ali@example.com');
    const f = fakeFetch(() => ({ body: { data: profile(h) } }));
    const p = await new Avatarak({ apiKey: 'avk_k', baseUrl: 'https://a.test/', fetch: f }).profile('Ali@example.com');
    assert.equal(p.username, 'ali');
    assert.equal(f.calls[0].url, `https://a.test/api/v1/profiles/${h}`);
    assert.equal(f.calls[0].init.headers.Authorization, 'Bearer avk_k');
    assert.ok(!JSON.stringify(f.calls[0]).includes('ali@example.com'), 'the address never leaves the machine');
});

test('profile: 404 is null, other errors are typed', async () => {
    const api = (status, body, headers) => new Avatarak({ apiKey: 'k', fetch: fakeFetch(() => ({ status, body, headers })) });
    assert.equal(await api(404, { message: 'Profile not found.' }).profile('a@b.ir'), null);
    await assert.rejects(api(401, { message: 'Invalid or missing API key.' }).profile('a@b.ir'), (e) => e instanceof AuthenticationError && e.status === 401);
    await assert.rejects(api(429, { message: 'Rate limit exceeded.' }, { 'retry-after': '17' }).profile('a@b.ir'), (e) => e instanceof RateLimitError && e.retryAfter === 17);
    await assert.rejects(api(500, { message: 'boom' }).profile('a@b.ir'), (e) => e instanceof ApiError && e.status === 500 && !(e instanceof AuthenticationError));
});

test('a missing key fails before any request', async () => {
    const f = fakeFetch(() => ({}));
    await assert.rejects(new Avatarak({ fetch: f }).profile('a@b.ir'), AuthenticationError);
    assert.equal(f.calls.length, 0);
});

test('network failures become ApiError', async () => {
    const api = new Avatarak({ apiKey: 'k', fetch: async () => { throw new TypeError('fetch failed'); } });
    await assert.rejects(api.profile('a@b.ir'), /Could not reach/);
});

test('lookup dedupes, batches by 100 and keeps unknowns as null', async () => {
    const emails = Array.from({ length: 230 }, (_, i) => `user${i}@example.com`).concat('USER0@example.com');
    const known = sha('user5@example.com');
    const f = fakeFetch((url, init) => {
        const { hashes } = JSON.parse(init.body);
        return { body: { data: Object.fromEntries(hashes.map((h) => [h, h === known ? profile(h) : null])) } };
    });
    const out = await new Avatarak({ apiKey: 'k', fetch: f }).lookup(emails);

    assert.equal(MAX_BATCH, 100);
    assert.equal(Object.keys(out).length, 230);
    assert.deepEqual(f.calls.map((c) => JSON.parse(c.init.body).hashes.length), [100, 100, 30]);
    assert.equal(out[known].username, 'ali');
    assert.equal(out[sha('user6@example.com')], null);
    assert.equal(f.calls[0].init.method, 'POST');
});

test('lookup of nothing makes no request', async () => {
    const f = fakeFetch(() => ({}));
    assert.deepEqual(await new Avatarak({ apiKey: 'k', fetch: f }).lookup([]), {});
    assert.equal(f.calls.length, 0);
});
