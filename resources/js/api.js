import { i18n } from './i18n';

function xsrf() {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}

export class ApiError extends Error {
    constructor(status, body) {
        super(body?.message || `HTTP ${status}`);
        this.status = status;
        this.errors = body?.errors || {};
    }
}

export async function api(method, url, body) {
    const headers = { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf(), 'X-Locale': i18n.locale };
    const opts = { method, headers, credentials: 'same-origin' };
    if (body instanceof FormData) {
        opts.body = body;
    } else if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(body);
    }
    const res = await fetch(url, opts);
    const data = res.status === 204 ? null : await res.json().catch(() => null);
    if (!res.ok) throw new ApiError(res.status, data);
    return data;
}
