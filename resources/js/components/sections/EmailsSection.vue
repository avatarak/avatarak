<script setup>
import { ref } from 'vue';
import { api, ApiError } from '../../api';
import { state, setAccount, toast } from '../../store';
import { t } from '../../i18n';

const newEmail = ref('');
const busy = ref(false);
const error = ref('');

async function refresh() { setAccount(await api('GET', '/account')); }

async function act(fn, okMsg) {
    error.value = '';
    try {
        await fn();
        await refresh();
        if (okMsg) toast(okMsg);
    } catch (e) {
        if (!(e instanceof ApiError)) throw e;
        error.value = Object.values(e.errors)[0]?.[0] || e.message;
    }
}

async function add() {
    busy.value = true;
    await act(async () => { await api('POST', '/account/emails', { email: newEmail.value }); newEmail.value = ''; }, t('emailSent'));
    busy.value = false;
}
const makePrimary = (e) => act(() => api('POST', `/account/emails/${e.id}/primary`), t('saved'));
const resend = (e) => act(() => api('POST', `/account/emails/${e.id}/resend`), t('emailSent'));
const setPublic = (e, v) => act(() => api('PATCH', `/account/emails/${e.id}`, { is_public: v }), t('saved'));
const remove = (e) => { if (confirm(t('confirmDelete'))) act(() => api('DELETE', `/account/emails/${e.id}`)); };
</script>

<template>
    <div>
        <ul class="space-y-3">
            <li v-for="e in state.account.emails" :key="e.id" class="rounded-[8px] border border-line p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span dir="ltr" class="break-all font-medium">{{ e.email }}</span>
                    <span v-if="e.is_primary" class="rounded-full bg-brand/10 px-2 py-0.5 text-xs font-semibold text-brand">{{ t('primary') }}</span>
                    <span class="rounded-full px-2 py-0.5 text-xs" :class="e.verified ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
                        {{ e.verified ? t('verified') : t('pending') }}
                    </span>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                    <label class="me-2 inline-flex items-center gap-2">
                        <input type="checkbox" :checked="e.is_public" class="size-4 accent-[var(--brand)]" @change="setPublic(e, $event.target.checked)">
                        {{ t('publicEmail') }}
                    </label>
                    <button v-if="e.verified && !e.is_primary" class="btn btn-ghost !min-h-0 !px-3 !py-1 text-sm" @click="makePrimary(e)">{{ t('makePrimary') }}</button>
                    <button v-if="!e.verified" class="btn btn-ghost !min-h-0 !px-3 !py-1 text-sm" @click="resend(e)">{{ t('resend') }}</button>
                    <button v-if="!e.is_primary" class="btn btn-danger !min-h-0 !px-3 !py-1 text-sm" @click="remove(e)">{{ t('delete') }}</button>
                </div>
            </li>
        </ul>

        <form class="mt-6 flex flex-col gap-2" @submit.prevent="add">
            <input v-model="newEmail" type="email" dir="ltr" class="field" :placeholder="t('email')" :aria-label="t('email')" required>
            <button class="btn btn-primary" :disabled="busy">{{ t('addEmail') }}</button>
        </form>
        <p v-if="error" class="mt-2 text-sm text-red-600" role="alert">{{ error }}</p>
    </div>
</template>
