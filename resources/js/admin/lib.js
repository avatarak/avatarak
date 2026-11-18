import { reactive, ref, watch, onBeforeUnmount } from 'vue';
import { api, ApiError } from '../api';
import { i18n } from '../i18n';

export const adminApi = (method, url, body) => api(method, '/admin/api' + url, body);

const loc = () => (i18n.locale === 'fa' ? 'fa-IR' : 'en-US');
export const fmtNum = (n) => new Intl.NumberFormat(loc()).format(n ?? 0);
export const fmtDate = (iso) => (iso ? new Date(iso).toLocaleDateString(loc(), { year: 'numeric', month: 'short', day: 'numeric' }) : '—');
export const fmtDateTime = (iso) => (iso ? new Date(iso).toLocaleString(loc(), { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—');
export const fmtDay = (d) => new Date(`${d}T00:00:00`).toLocaleDateString(loc(), { month: 'short', day: 'numeric' });

export function ago(iso) {
    if (!iso) return '—';
    const sec = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const rtf = new Intl.RelativeTimeFormat(loc(), { numeric: 'auto' });
    for (const [unit, s] of [['year', 31536000], ['month', 2592000], ['day', 86400], ['hour', 3600], ['minute', 60]]) {
        if (Math.abs(sec) >= s) return rtf.format(Math.round(sec / s), unit);
    }
    return rtf.format(0, 'second');
}

export const errorText = (e) => (e instanceof ApiError ? (Object.values(e.errors || {})[0]?.[0] || e.message) : String(e?.message || e));

export function qs(params) {
    const q = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) if (v !== '' && v != null) q.set(k, v);
    const s = q.toString();
    return s ? `?${s}` : '';
}

/** A paginated, filterable list: change `params` and it reloads (search text is debounced). */
export function useList(path, initial = {}) {
    const params = reactive({ q: '', page: 1, ...initial });
    const rows = ref([]);
    const meta = ref({ page: 1, last_page: 1, total: 0 });
    const extra = ref({});
    const loading = ref(true);
    const error = ref('');
    let timer; let seq = 0;

    async function load() {
        const mine = ++seq;
        loading.value = true; error.value = '';
        try {
            const r = await adminApi('GET', path + qs(params));
            if (mine !== seq) return;                  // a newer request is already in flight
            rows.value = r.data; meta.value = r.meta; extra.value = r;
        } catch (e) {
            if (mine === seq) error.value = errorText(e);
        } finally {
            if (mine === seq) loading.value = false;
        }
    }

    // filters and page reload at once; typing waits 300 ms, and a new search goes back to page 1
    watch(() => JSON.stringify({ ...params, q: undefined }), load);
    watch(() => params.q, () => {
        clearTimeout(timer);
        timer = setTimeout(() => (params.page === 1 ? load() : (params.page = 1)), 300);
    });
    onBeforeUnmount(() => clearTimeout(timer));
    load();

    // reactive() unwraps the refs, so templates can write list.rows / list.loading directly
    return reactive({ params, rows, meta, extra, loading, error, load });
}
