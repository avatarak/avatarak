<script setup>
import { ref, reactive, onMounted } from 'vue';
import { adminApi, errorText, fmtDateTime } from '../../admin/lib';
import { t } from '../../i18n';
import Badge from './Badge.vue';

const settings = ref([]);
const anns = ref([]);
const error = ref('');
const blank = () => ({ id: null, message_fa: '', message_en: '', level: 'info', active: true, dismissible: true, starts_at: '', ends_at: '' });
const form = reactive(blank());
const editing = ref(false);
const busy = ref(false);

async function load() {
    try {
        settings.value = (await adminApi('GET', '/settings')).settings;
        anns.value = (await adminApi('GET', '/announcements')).data;
    } catch (e) { error.value = errorText(e); }
}
onMounted(load);

async function setValue(s, value) {
    error.value = '';
    try { settings.value = (await adminApi('PUT', `/settings/${s.key}`, { value })).settings; } catch (e) { error.value = errorText(e); }
}
async function reset(s) {
    error.value = '';
    try { settings.value = (await adminApi('DELETE', `/settings/${s.key}`)).settings; } catch (e) { error.value = errorText(e); }
}

const local = (iso) => (iso ? iso.slice(0, 16) : '');
function edit(a) { Object.assign(form, { ...a, message_en: a.message_en || '', starts_at: local(a.starts_at), ends_at: local(a.ends_at) }); editing.value = true; }
function fresh() { Object.assign(form, blank()); editing.value = true; }
async function save() {
    busy.value = true; error.value = '';
    const body = { message_fa: form.message_fa, message_en: form.message_en || null, level: form.level, active: form.active, dismissible: form.dismissible, starts_at: form.starts_at || null, ends_at: form.ends_at || null };
    try {
        form.id ? await adminApi('PUT', `/announcements/${form.id}`, body) : await adminApi('POST', '/announcements', body);
        editing.value = false; await load();
    } catch (e) { error.value = errorText(e); } finally { busy.value = false; }
}
async function remove(a) { if (!confirm(t('a_ann_delete') + '?')) return; await adminApi('DELETE', `/announcements/${a.id}`); await load(); }
</script>

<template>
    <div class="space-y-8">
        <p v-if="error" class="rounded-[6px] bg-red-50 p-3 text-sm text-red-800" role="alert">{{ error }}</p>

        <section class="card p-5">
            <h2 class="text-lg font-bold">{{ t('a_set_title') }}</h2>
            <p class="hint mt-1">{{ t('a_set_help') }}</p>
            <ul class="mt-4 divide-y divide-line">
                <li v-for="s in settings" :key="s.key" class="flex flex-wrap items-center justify-between gap-3 py-4">
                    <div class="min-w-0 flex-1">
                        <div class="font-medium">{{ t('a_set_' + s.key) }}</div>
                        <p class="hint">{{ t('a_set_' + s.key + '_d') }}</p>
                        <Badge :tone="s.overridden ? 'amber' : 'blue'" class="mt-1">{{ s.overridden ? t('a_set_panel') : t('a_set_default') }}</Badge>
                    </div>
                    <div class="flex items-center gap-2">
                        <button v-if="s.type === 'bool'" type="button" role="switch" :aria-checked="s.value" :aria-label="t('a_set_' + s.key)"
                                class="relative h-7 w-12 rounded-full transition-colors" :class="s.value ? 'bg-brand' : 'bg-line'" @click="setValue(s, !s.value)">
                            <span class="absolute top-0.5 size-6 rounded-full bg-white shadow transition-all" :class="s.value ? 'start-[1.375rem]' : 'start-0.5'"></span>
                        </button>
                        <select v-else :value="s.value" class="field !w-auto" :aria-label="t('a_set_' + s.key)" @change="setValue(s, $event.target.value)">
                            <option v-for="o in s.options" :key="o" :value="o">{{ o }}</option>
                        </select>
                        <button v-if="s.overridden" type="button" class="btn btn-ghost !min-h-0 !px-3 !py-1 text-sm" @click="reset(s)">{{ t('a_set_reset') }}</button>
                    </div>
                </li>
            </ul>
        </section>

        <section class="card p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div><h2 class="text-lg font-bold">{{ t('a_ann_title') }}</h2><p class="hint mt-1">{{ t('a_ann_help') }}</p></div>
                <button type="button" class="btn btn-primary !min-h-0 !px-4 !py-2 text-sm" @click="fresh">{{ t('a_ann_new') }}</button>
            </div>

            <form v-if="editing" class="mt-4 grid gap-3 rounded-[8px] border border-line p-4" @submit.prevent="save">
                <label class="grid gap-1 text-sm">{{ t('a_ann_fa') }}<textarea v-model="form.message_fa" class="field min-h-[72px]" maxlength="500" required></textarea></label>
                <label class="grid gap-1 text-sm">{{ t('a_ann_en') }}<textarea v-model="form.message_en" dir="ltr" class="field min-h-[72px]" maxlength="500"></textarea></label>
                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="grid gap-1 text-sm">{{ t('a_ann_level') }}
                        <select v-model="form.level" class="field"><option v-for="l in ['info', 'success', 'warning']" :key="l" :value="l">{{ t('a_ann_' + l) }}</option></select></label>
                    <label class="grid gap-1 text-sm">{{ t('a_ann_starts') }}<input v-model="form.starts_at" type="datetime-local" dir="ltr" class="field"></label>
                    <label class="grid gap-1 text-sm">{{ t('a_ann_ends') }}<input v-model="form.ends_at" type="datetime-local" dir="ltr" class="field"></label>
                </div>
                <div class="flex flex-wrap gap-5 text-sm">
                    <label class="flex items-center gap-2"><input v-model="form.active" type="checkbox"> {{ t('a_ann_active') }}</label>
                    <label class="flex items-center gap-2"><input v-model="form.dismissible" type="checkbox"> {{ t('a_ann_dismissible') }}</label>
                </div>
                <div class="flex gap-2">
                    <button class="btn btn-primary" :disabled="busy">{{ t('a_ann_save') }}</button>
                    <button type="button" class="btn btn-ghost" @click="editing = false">{{ t('a_cancel') }}</button>
                </div>
            </form>

            <p v-if="!anns.length" class="mt-4 text-muted">{{ t('a_ann_none') }}</p>
            <ul v-else class="mt-4 divide-y divide-line">
                <li v-for="a in anns" :key="a.id" class="flex flex-wrap items-start justify-between gap-3 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="whitespace-pre-line text-sm">{{ a.message_fa }}</p>
                        <p v-if="a.message_en" dir="ltr" class="mt-1 text-start text-sm text-muted">{{ a.message_en }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted">
                            <Badge :tone="a.live ? 'green' : 'gray'">{{ a.live ? t('a_ann_live') : t('a_ann_idle') }}</Badge>
                            <Badge tone="blue">{{ t('a_ann_' + a.level) }}</Badge>
                            <span v-if="a.starts_at">{{ fmtDateTime(a.starts_at) }}</span><span v-if="a.ends_at">→ {{ fmtDateTime(a.ends_at) }}</span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-ghost !min-h-0 !px-3 !py-1 text-sm" @click="edit(a)">{{ t('a_ann_edit') }}</button>
                        <button type="button" class="btn btn-danger !min-h-0 !px-3 !py-1 text-sm" @click="remove(a)">{{ t('a_ann_delete') }}</button>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
