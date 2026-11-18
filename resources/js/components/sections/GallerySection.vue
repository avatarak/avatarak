<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { api, upload, ApiError } from '../../api';
import { state, refresh, toast } from '../../store';
import { t } from '../../i18n';
import Icon from '../Icon.vue';
import SaveBar from '../SaveBar.vue';

defineEmits(['goto']);
const gallery = ref({ limit: 0, images: [] });
const progress = ref(null);
const error = ref('');
const input = ref(null);

const load = async () => (gallery.value = await api('GET', '/account/gallery'));
onMounted(load);

async function add(file) {
    error.value = '';
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return (error.value = t('errType'));
    progress.value = 0;
    try {
        await upload('/account/gallery', 'image', file, file.name, (p) => (progress.value = p)).promise;
        await load(); await refresh(); toast(t('saved'));
    } catch (e) { error.value = e instanceof ApiError ? (Object.values(e.errors || {})[0]?.[0] || e.message) : t('uploadFailed'); } finally { progress.value = null; if (input.value) input.value.value = ''; }
}
async function act(fn) {
    error.value = '';
    try { gallery.value = await fn(); await refresh(); } catch (e) { error.value = e instanceof ApiError ? (Object.values(e.errors || {})[0]?.[0] || e.message) : t('error'); }
}
const caption = (g, v) => act(() => api('PATCH', `/account/gallery/${g.id}`, { caption: v }));
const remove = (g) => confirm(t('confirmDelete')) && act(() => api('DELETE', `/account/gallery/${g.id}`));
function move(i, d) {
    const ids = gallery.value.images.map((g) => g.id);
    const j = i + d;
    if (j < 0 || j >= ids.length) return;
    [ids[i], ids[j]] = [ids[j], ids[i]];
    act(() => api('POST', '/account/gallery/order', { order: ids }));
}

// ---- free-text sections ----
const limit = computed(() => state.account.profile.section_limit ?? 0);
const sections = reactive({ list: (state.account.profile.custom_sections || []).map((s) => ({ ...s })), busy: false, msg: '', err: '' });
async function saveSections() {
    sections.busy = true; sections.err = ''; sections.msg = '';
    try { await api('PUT', '/account/sections', { sections: sections.list.filter((s) => s.title.trim() || s.body.trim()) }); await refresh(); toast(t('saved')); } catch (e) { sections.err = e instanceof ApiError ? (Object.values(e.errors || {})[0]?.[0] || e.message) : t('error'); } finally { sections.busy = false; }
}
const addSection = () => sections.list.length < limit.value && sections.list.push({ title: '', body: '' });
</script>

<template>
    <div>
        <h3 class="text-lg font-bold">{{ t('gaPhotos') }}</h3>
        <p class="hint mt-1">{{ t('gaPhotosHelp', { n: gallery.limit }) }}</p>
        <ul v-if="gallery.images.length" class="mt-4 grid grid-cols-2 gap-3">
            <li v-for="(g, i) in gallery.images" :key="g.id" class="rounded-[8px] border border-line p-2">
                <img :src="`/account/gallery/${g.id}/thumb`" alt="" class="aspect-square w-full rounded-[4px] bg-line object-cover">
                <input :value="g.caption" class="field mt-2 !min-h-0 !py-1 text-sm" :placeholder="t('gaCaption')" :aria-label="t('gaCaption')" maxlength="140" @change="caption(g, $event.target.value)">
                <div class="mt-2 flex items-center justify-between">
                    <span class="flex gap-1">
                        <button type="button" class="icon-btn" :disabled="i === 0" :aria-label="t('gaUp')" @click="move(i, -1)"><Icon name="chevron" :size="18" class="-rotate-90" /></button>
                        <button type="button" class="icon-btn" :disabled="i === gallery.images.length - 1" :aria-label="t('gaDown')" @click="move(i, 1)"><Icon name="chevron" :size="18" class="rotate-90" /></button>
                    </span>
                    <button type="button" class="icon-btn text-red-600" :aria-label="t('delete')" @click="remove(g)"><Icon name="trash" :size="18" /></button>
                </div>
            </li>
        </ul>
        <p v-else class="mt-4 text-muted">{{ t('gaEmpty') }}</p>

        <div v-if="gallery.images.length < gallery.limit" class="mt-4">
            <input ref="input" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="add($event.target.files[0])">
            <button type="button" class="btn btn-ghost w-full" :disabled="progress !== null" @click="input.click()"><Icon name="plus" :size="18" /> {{ progress === null ? t('gaAdd') : progress + '%' }}</button>
        </div>
        <p v-else-if="gallery.limit" class="mt-4 text-sm text-amber-800">{{ t('gaFull') }} <button type="button" class="font-semibold underline underline-offset-2" @click="$emit('goto', 'billing')">{{ t('dmUpgrade') }}</button></p>
        <p v-if="error" class="mt-2 text-sm text-red-600" role="alert">{{ error }}</p>

        <h3 class="mt-10 text-lg font-bold">{{ t('csTitle') }}</h3>
        <p class="hint mt-1">{{ t('csHelp', { n: limit }) }}</p>
        <form class="mt-4 space-y-4" @submit.prevent="saveSections">
            <div v-for="(s, i) in sections.list" :key="i" class="rounded-[8px] border border-line p-3">
                <input v-model="s.title" class="field" maxlength="60" :placeholder="t('csName')" :aria-label="t('csName')">
                <textarea v-model="s.body" class="field mt-2 min-h-[110px]" maxlength="1500" :placeholder="t('csBody')" :aria-label="t('csBody')"></textarea>
                <button type="button" class="btn btn-danger mt-2 !min-h-0 !px-3 !py-1 text-sm" @click="sections.list.splice(i, 1)">{{ t('delete') }}</button>
            </div>
            <button v-if="sections.list.length < limit" type="button" class="btn btn-ghost w-full" @click="addSection"><Icon name="plus" :size="18" /> {{ t('csAdd') }}</button>
            <p v-else-if="limit" class="text-sm text-amber-800">{{ t('csFull') }} <button type="button" class="font-semibold underline underline-offset-2" @click="$emit('goto', 'billing')">{{ t('dmUpgrade') }}</button></p>
            <SaveBar :busy="sections.busy" :error="sections.err" />
        </form>
    </div>
</template>
