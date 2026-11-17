<script setup>
import { ref, computed } from 'vue';
import { api, ApiError } from '../../api';
import { state, setAccount, toast } from '../../store';
import { copyText } from '../../composables';
import { t } from '../../i18n';

const busy = ref(false);
const drag = ref(false);
const error = ref('');
const input = ref(null);

const src = computed(() => state.account.profile.has_avatar ? `/account/avatar?v=${state.account.profile.avatar_version}` : null);
const shown = computed(() => state.account.emails.filter((e) => e.verified && e.is_public));
const origin = window.location.origin;

async function upload(file) {
    if (!file) return;
    busy.value = true; error.value = '';
    try {
        const fd = new FormData();
        fd.append('avatar', file);
        await api('POST', '/account/avatar', fd);
        setAccount(await api('GET', '/account'));
    } catch (e) {
        if (!(e instanceof ApiError)) throw e;
        error.value = Object.values(e.errors)[0]?.[0] || e.message;
    } finally {
        busy.value = false;
    }
}

async function remove() {
    busy.value = true;
    try {
        await api('DELETE', '/account/avatar');
        setAccount(await api('GET', '/account'));
        toast(t('saved'));
    } finally {
        busy.value = false;
    }
}
function onDrop(e) { drag.value = false; upload(e.dataTransfer.files[0]); }
</script>

<template>
    <div>
        <h2 class="font-display text-2xl font-bold">{{ t('avatarTitle') }}</h2>
        <p class="hint mt-2">{{ t('avatarHint') }}</p>

        <div class="mt-6 flex flex-wrap items-center gap-6">
            <div class="grid size-40 place-items-center overflow-hidden rounded-full border border-line bg-line transition-shadow"
                 :class="{ 'ring-4 ring-brand/30': drag }"
                 @dragover.prevent="drag = true" @dragleave="drag = false" @drop.prevent="onDrop">
                <img v-if="src" :src="src" alt="" class="size-full object-cover">
                <span v-else class="px-4 text-center text-sm text-muted">{{ t('dropHere') }}</span>
            </div>
            <div class="flex flex-col gap-2">
                <input ref="input" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="upload($event.target.files[0]); $event.target.value = ''">
                <button class="btn btn-primary" :disabled="busy" @click="input.click()">{{ busy ? t('uploading') : t('upload') }}</button>
                <button v-if="src" class="btn btn-ghost" :disabled="busy" @click="remove">{{ t('removeAvatar') }}</button>
                <p v-if="error" class="text-sm text-red-600" role="alert">{{ error }}</p>
            </div>
        </div>

        <h3 class="mt-10 font-display text-lg font-bold">{{ t('whereShown') }}</h3>
        <p class="hint mt-1">{{ t('whereShownHint') }}</p>
        <p v-if="!shown.length" class="mt-3 rounded-lg border border-line bg-surface p-3 text-sm">{{ t('noPublicEmail') }}</p>
        <ul v-else class="mt-3 space-y-2">
            <li v-for="e in shown" :key="e.id" class="card p-3">
                <div dir="ltr" class="text-sm font-medium">{{ e.email }}</div>
                <div class="mt-1 flex items-center gap-2">
                    <code dir="ltr" class="min-w-0 flex-1 truncate rounded bg-canvas px-2 py-1 text-xs">{{ origin }}/avatar/{{ e.email_hash }}?s=200</code>
                    <button class="btn btn-ghost !px-3 !py-1 text-sm" @click="copyText(`${origin}/avatar/${e.email_hash}?s=200`)">{{ t('copy') }}</button>
                </div>
            </li>
        </ul>
    </div>
</template>
