import { ref } from 'vue';
import { api, ApiError } from './api';
import { setAccount, toast } from './store';
import { t } from './i18n';

/** Runs a PATCH, refreshes account state, reports validation errors. */
export function useSubmit(method, url, getBody) {
    const busy = ref(false);
    const errors = ref({});
    const message = ref('');

    async function run() {
        busy.value = true; errors.value = {}; message.value = '';
        try {
            setAccount(await api(method, url, getBody()));
            toast(t('saved'));
            return true;
        } catch (e) {
            if (!(e instanceof ApiError)) throw e;
            errors.value = e.errors;
            message.value = Object.keys(e.errors).length ? '' : e.message;
            return false;
        } finally {
            busy.value = false;
        }
    }
    const err = (k) => errors.value[k]?.[0] || (Object.entries(errors.value).find(([n]) => n.startsWith(k + '.'))?.[1]?.[0]);

    return { busy, errors, message, run, err };
}

export async function copyText(text) {
    try { await navigator.clipboard.writeText(text); toast(t('copied')); } catch { /* clipboard unavailable */ }
}
