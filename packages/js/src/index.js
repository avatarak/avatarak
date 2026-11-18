/**
 * @avatarak/client — zero-dependency client for https://avatarak.ir (or your own install).
 *
 *   import { Avatarak, avatarUrl } from '@avatarak/client';
 *   await avatarUrl('ali@example.com', { size: 96 });          // no API key, no request
 *   const api = new Avatarak({ apiKey: 'avk_…' });             // keep the key on your SERVER
 *   await api.profile('ali@example.com');                       // profile object, or null
 *
 * The email address never leaves the machine: only its SHA-256 hash is used.
 */

export const DEFAULT_BASE_URL = 'https://avatarak.ir';
export const MAX_BATCH = 100;

export class ApiError extends Error {
    constructor(message, status = 0, body = null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.body = body;
    }
}
export class AuthenticationError extends ApiError {
    constructor(message, body) { super(message, 401, body); this.name = 'AuthenticationError'; }
}
export class RateLimitError extends ApiError {
    constructor(message, body, retryAfter = 60) { super(message, 429, body); this.name = 'RateLimitError'; this.retryAfter = retryAfter; }
}

const HEX64 = /^[a-f0-9]{64}$/i;

async function sha256Hex(text) {
    const bytes = new TextEncoder().encode(text);
    const subtle = globalThis.crypto?.subtle ?? (await import('node:crypto')).webcrypto.subtle;
    const digest = await subtle.digest('SHA-256', bytes);
    return [...new Uint8Array(digest)].map((b) => b.toString(16).padStart(2, '0')).join('');
}

/** SHA-256 of the trimmed, lower-cased email — the identifier used everywhere. A 64-hex string passes through. */
export async function hashEmail(emailOrHash) {
    const s = String(emailOrHash).trim();
    return HEX64.test(s) ? s.toLowerCase() : sha256Hex(s.toLowerCase());
}

/** Build an avatar URL from a hash you already have (synchronous). */
export function avatarUrlFromHash(hash, { size, default: fallback, baseUrl = DEFAULT_BASE_URL } = {}) {
    const q = new URLSearchParams();
    if (size) q.set('s', String(size));
    if (fallback && fallback !== 'mp') q.set('d', fallback);          // mp | identicon | blank | 404
    const qs = q.toString();
    return `${baseUrl.replace(/\/+$/, '')}/avatar/${hash.toLowerCase()}${qs ? `?${qs}` : ''}`;
}

/** Avatar URL for an email (or hash). Needs no API key and makes no request. */
export async function avatarUrl(emailOrHash, opts = {}) {
    return avatarUrlFromHash(await hashEmail(emailOrHash), opts);
}

export class Avatarak {
    /**
     * @param {{apiKey?: string, baseUrl?: string, fetch?: typeof fetch, timeoutMs?: number}} [options]
     */
    constructor({ apiKey, baseUrl = DEFAULT_BASE_URL, fetch: fetchImpl, timeoutMs = 8000 } = {}) {
        this.apiKey = apiKey;
        this.baseUrl = baseUrl.replace(/\/+$/, '');
        this.fetch = fetchImpl ?? globalThis.fetch?.bind(globalThis);
        this.timeoutMs = timeoutMs;
    }

    hash(emailOrHash) { return hashEmail(emailOrHash); }

    async avatarUrl(emailOrHash, opts = {}) {
        return avatarUrlFromHash(await hashEmail(emailOrHash), { baseUrl: this.baseUrl, ...opts });
    }

    /** @returns {Promise<object|null>} the profile, or null when there is no public profile for this address */
    async profile(emailOrHash) {
        try {
            const r = await this.#request('GET', `/api/v1/profiles/${await hashEmail(emailOrHash)}`);
            return r.data;
        } catch (e) {
            if (e instanceof ApiError && e.status === 404) return null;
            throw e;
        }
    }

    /** @returns {Promise<Record<string, object|null>>} keyed by hash; unknown addresses are null. Any number of inputs. */
    async lookup(emailsOrHashes) {
        const hashes = [...new Set(await Promise.all([...emailsOrHashes].map(hashEmail)))];
        const out = {};
        for (let i = 0; i < hashes.length; i += MAX_BATCH) {
            const chunk = hashes.slice(i, i + MAX_BATCH);
            const r = await this.#request('POST', '/api/v1/profiles/lookup', { hashes: chunk });
            for (const h of chunk) out[h] = r.data?.[h] ?? null;
        }
        return out;
    }

    async #request(method, path, json) {
        if (!this.apiKey) throw new AuthenticationError('An API key is required for profile lookups.');
        if (!this.fetch) throw new ApiError('No fetch implementation available; pass options.fetch.');

        const headers = { Accept: 'application/json', Authorization: `Bearer ${this.apiKey}` };
        if (json !== undefined) headers['Content-Type'] = 'application/json';

        const ctl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        const timer = ctl ? setTimeout(() => ctl.abort(), this.timeoutMs) : null;
        let res;
        try {
            res = await this.fetch(this.baseUrl + path, { method, headers, body: json === undefined ? undefined : JSON.stringify(json), signal: ctl?.signal });
        } catch (e) {
            throw new ApiError(`Could not reach the Avatarak API: ${e.message}`);
        } finally {
            if (timer) clearTimeout(timer);
        }

        const body = await res.json().catch(() => null);
        const message = body?.message || `HTTP ${res.status}`;
        if (res.ok && body) return body;
        if (res.status === 401) throw new AuthenticationError(message, body);
        if (res.status === 429) throw new RateLimitError(message, body, Number(res.headers.get('retry-after')) || 60);
        throw new ApiError(message, res.status, body);
    }
}
