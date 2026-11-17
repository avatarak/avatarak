<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { api, upload, ApiError } from '../../api';
import { state, refresh, toast } from '../../store';
import { copyText } from '../../composables';
import { t } from '../../i18n';
import Icon from '../Icon.vue';
import CropModal from '../CropModal.vue';

const MAX_SOURCE = 20 * 1024 * 1024;
const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

const input = ref(null);
const cropFile = ref(null);
const cropOpen = ref(false);
const openCrop = (f) => { cropFile.value = f; cropOpen.value = true; };
const closeCrop = () => { cropOpen.value = false; setTimeout(() => { if (!cropOpen.value) cropFile.value = null; }, 260); };
const dragging = ref(false);
const progress = ref(null);   // null | 0..100
const error = ref('');
let current = null;           // active upload handle

const avatars = computed(() => state.account.avatars || []);
const hasActive = computed(() => avatars.value.some((a) => a.active));
const shown = computed(() => state.account.emails.filter((e) => e.verified && e.is_public));
const origin = window.location.origin;

function pick(file) {
    error.value = '';
    if (!file) return;
    if (!TYPES.includes(file.type)) return (error.value = t('errType'));
    if (file.size > MAX_SOURCE) return (error.value = t('errSize'));
    openCrop(file);
}

async function send(blob) {
    closeCrop();
    progress.value = 0;
    error.value = '';
    current = upload('/account/avatars', 'avatar', blob, 'avatar.jpg', (p) => (progress.value = p));
    try {
        await current.promise;
        await refresh();
        toast(t('saved'));
    } catch (e) {
        if (e instanceof ApiError && e.status === -1) return;       // cancelled by the user
        error.value = (e instanceof ApiError && (Object.values(e.errors)[0]?.[0] || e.message)) || t('uploadFailed');
    } finally {
        progress.value = null;
        current = null;
    }
}

async function act(fn) {
    error.value = '';
    try { await fn(); await refresh(); } catch (e) {
        if (!(e instanceof ApiError)) throw e;
        error.value = Object.values(e.errors)[0]?.[0] || e.message;
    }
}
const activate = (a) => act(() => api('POST', `/account/avatars/${a.id}/activate`));
const remove = (a) => { if (confirm(t('confirmDelete'))) act(() => api('DELETE', `/account/avatars/${a.id}`)); };
const useNone = () => act(() => api('DELETE', '/account/avatar'));

function onDrop(e) { dragging.value = false; pick(e.dataTransfer?.files?.[0]); }
function onPaste(e) {
    const f = [...(e.clipboardData?.files || [])].find((x) => x.type.startsWith('image/'));
    if (f) { e.preventDefault(); pick(f); }
}
onMounted(() => document.addEventListener('paste', onPaste));
onBeforeUnmount(() => { document.removeEventListener('paste', onPaste); current?.abort(); });
</script>

<template>
    <div @dragover.prevent="dragging = true" @dragleave.self="dragging = false" @drop.prevent="onDrop">
        <input ref="input" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" tabindex="-1"
               @change="pick($event.target.files[0]); $event.target.value = ''">

        <button type="button" class="btn btn-primary w-full" :disabled="progress !== null || avatars.length >= 10" @click="input.click()">
            <Icon name="camera" :size="20" /> {{ t('addAvatar') }}
        </button>
        <p class="hint mt-2">{{ t('pasteHint') }} · {{ t('avatarLimit') }}</p>

        <div v-if="progress !== null" class="mt-4" role="status" aria-live="polite">
            <div class="flex items-center justify-between text-sm"><span>{{ t('uploading') }}</span><span dir="ltr">{{ progress }}%</span></div>
            <div class="mt-1 h-2 overflow-hidden rounded-full bg-line"><div class="h-full bg-brand transition-all" :style="{ width: progress + '%' }"></div></div>
            <button type="button" class="btn btn-quiet mt-2 !min-h-0 !py-1 text-sm" @click="current?.abort()">{{ t('cancel') }}</button>
        </div>
        <p v-if="error" class="mt-3 rounded-[4px] border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">{{ error }}</p>

        <div class="mt-5 rounded-[8px] border-2 border-dashed p-3 transition-colors" :class="dragging ? 'border-brand bg-brand/5' : 'border-transparent'">
            <p v-if="!avatars.length" class="py-8 text-center text-muted">{{ t('emptyAvatars') }}</p>
            <ul v-else class="grid grid-cols-2 gap-4">
                <li v-for="a in avatars" :key="a.id">
                    <div class="relative aspect-square overflow-hidden rounded-[8px] bg-line" :class="a.active ? 'ring-[3px] ring-brand ring-offset-2' : ''">
                        <img :src="`/account/avatars/${a.id}/image`" alt="" class="size-full object-cover" loading="lazy">
                        <span v-if="a.active" class="absolute start-2 top-2 inline-flex items-center gap-1 rounded-full bg-brand px-2 py-0.5 text-xs font-semibold text-white">
                            <Icon name="check" :size="12" /> {{ t('active') }}
                        </span>
                    </div>
                    <div class="mt-2 flex items-center justify-between gap-1">
                        <button v-if="!a.active" type="button" class="btn btn-ghost !min-h-0 !px-3 !py-1 text-sm" @click="activate(a)">{{ t('use') }}</button>
                        <span v-else></span>
                        <button type="button" class="icon-btn !size-9 text-muted hover:text-red-700" :title="t('deleteAvatar')" :aria-label="t('deleteAvatar')" @click="remove(a)">
                            <Icon name="trash" :size="18" />
                        </button>
                    </div>
                </li>
            </ul>
            <button v-if="hasActive" type="button" class="btn btn-quiet mt-3 w-full text-sm" @click="useNone">{{ t('noAvatarUse') }}</button>
        </div>

        <h3 class="mt-8 text-lg font-bold">{{ t('whereShown') }}</h3>
        <p class="hint mt-1">{{ t('whereShownHint') }}</p>
        <p v-if="!shown.length" class="mt-3 rounded-[4px] bg-soft p-3 text-sm">{{ t('noPublicEmail') }}</p>
        <ul v-else class="mt-3 space-y-2">
            <li v-for="e in shown" :key="e.id" class="rounded-[8px] border border-line p-3">
                <div dir="ltr" class="text-start text-sm font-medium">{{ e.email }}</div>
                <div class="mt-1 flex items-center gap-2">
                    <code dir="ltr" class="min-w-0 flex-1 truncate rounded bg-soft px-2 py-1 text-xs">{{ origin }}/avatar/{{ e.email_hash }}?s=200</code>
                    <button class="icon-btn !size-9" :title="t('copy')" :aria-label="t('copy')" @click="copyText(`${origin}/avatar/${e.email_hash}?s=200`)"><Icon name="copy" :size="18" /></button>
                </div>
            </li>
        </ul>

        <CropModal v-if="cropFile" :file="cropFile" :open="cropOpen" @done="send" @cancel="closeCrop" @error="(m) => { closeCrop(); error = m; }" />
    </div>
</template>
