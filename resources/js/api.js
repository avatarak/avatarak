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

/** Upload a file/blob with progress. Returns { promise, abort }. */
export function upload(url, field, blob, filename, onProgress) {
    const xhr = new XMLHttpRequest();
    const promise = new Promise((resolve, reject) => {
        xhr.open('POST', url);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-XSRF-TOKEN', xsrf());
        xhr.setRequestHeader('X-Locale', i18n.locale);
        xhr.upload.onprogress = (e) => e.lengthComputable && onProgress?.(Math.round((e.loaded / e.total) * 100));
        xhr.onload = () => {
            let body = null;
            try { body = JSON.parse(xhr.responseText); } catch { /* non-JSON error page */ }
            xhr.status >= 200 && xhr.status < 300 ? resolve(body) : reject(new ApiError(xhr.status, body));
        };
        xhr.onerror = () => reject(new ApiError(0, { message: 'Network error' }));
        xhr.onabort = () => reject(new ApiError(-1, { message: 'aborted' }));
        const fd = new FormData();
        fd.append(field, blob, filename);
        xhr.send(fd);
    });
    return { promise, abort: () => xhr.abort() };
}
