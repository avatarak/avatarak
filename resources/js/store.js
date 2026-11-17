import { reactive } from 'vue';
import { api, ApiError } from './api';

export const state = reactive({ ready: false, account: null, draft: null, toast: '' });

let toastTimer;
export function toast(msg) {
    state.toast = msg;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (state.toast = ''), 2200);
}

export function setAccount(a) {
    state.account = a;
    state.draft = a ? JSON.parse(JSON.stringify(a.profile)) : null;
}

export async function load() {
    try {
        setAccount(await api('GET', '/account'));
    } catch (e) {
        if (!(e instanceof ApiError) || e.status !== 401) throw e;
        setAccount(null);
    }
    state.ready = true;
}
