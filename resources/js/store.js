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

/** Re-fetch the account without discarding unsaved form edits in the draft. */
export async function refresh() {
    const a = await api('GET', '/account');
    state.account = a;
    if (state.draft) {
        for (const k of ['has_avatar', 'avatar_version', 'avatar_id']) state.draft[k] = a.profile[k];
    } else {
        state.draft = JSON.parse(JSON.stringify(a.profile));
    }
}
