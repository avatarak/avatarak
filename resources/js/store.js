import { reactive } from 'vue';
import { i18n } from './i18n';
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

/** Adopt an account that was fetched ahead of time (the sign-in animation fetches it while the check mark shows). */
export function adopt(a) {
    setAccount(a);
    state.ready = true;
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
        for (const k of ['has_avatar', 'avatar_version', 'avatar_id', 'custom_sections', 'section_limit']) state.draft[k] = a.profile[k];
    } else {
        state.draft = JSON.parse(JSON.stringify(a.profile));
    }
}

/** What every visitor may know: are sign-ups open, and which notices are live. */
export const siteStatus = reactive({ loaded: false, registration_open: true, announcements: [] });
export async function loadStatus() {
    try {
        Object.assign(siteStatus, await api('GET', '/site/status'), { loaded: true });
    } catch { siteStatus.loaded = true; }        // a notice must never break the page
}
