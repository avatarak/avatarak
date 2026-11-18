<script setup>
import { ref, reactive, onMounted } from 'vue';
import { api, ApiError } from '../../api';
import { toast } from '../../store';
import { copyText } from '../../composables';
import { t } from '../../i18n';

defineEmits(['goto']);
const data = ref(null);
const form = reactive({ url: '', events: ['profile.updated'] });
const secret = ref('');
const error = ref('');
const busy = ref(false);

const load = async () => (data.value = await api('GET', '/account/webhooks'));
onMounted(load);
const evKey = (e) => 'ev_' + e.replace('.', '_');
const fmt = (d) => (d ? new Date(d).toLocaleString() : t('never'));

async function add() {
    busy.value = true; error.value = ''; secret.value = '';
    try {
        const w = await api('POST', '/account/webhooks', { url: form.url, events: form.events });
        secret.value = w.secret; form.url = '';
        await load();
    } catch (e) { error.value = e instanceof ApiError ? (Object.values(e.errors || {})[0]?.[0] || e.message) : t('error'); } finally { busy.value = false; }
}
async function toggle(w) { await api('PATCH', `/account/webhooks/${w.id}`, { active: !w.active }); await load(); }
async function ping(w) { await api('POST', `/account/webhooks/${w.id}/ping`); toast(t('whPinged')); setTimeout(load, 1500); }
async function rotate(w) { if (!confirm(t('confirmDelete'))) return; secret.value = (await api('POST', `/account/webhooks/${w.id}/secret`)).secret; }
async function remove(w) { if (!confirm(t('confirmDelete'))) return; await api('DELETE', `/account/webhooks/${w.id}`); await load(); }
</script>

<template>
    <div v-if="data" class="mt-10 border-t border-line pt-8">
        <h3 class="text-lg font-bold">{{ t('whTitle') }}</h3>
        <p class="hint mt-1">{{ t('whHelp') }}</p>

        <div v-if="!data.limit" class="mt-4 rounded-[8px] border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
            {{ t('whNeedPlan') }} <button type="button" class="font-semibold underline underline-offset-2" @click="$emit('goto', 'billing')">{{ t('dmUpgrade') }}</button>
        </div>
        <form v-else-if="data.webhooks.length < data.limit" class="mt-4 grid gap-2" novalidate @submit.prevent="add">
            <input v-model="form.url" dir="ltr" class="field" placeholder="https://example.com/hooks/avatarak" :aria-label="t('whUrl')" required>
            <fieldset class="grid gap-1 text-sm">
                <legend class="label">{{ t('whEvents') }}</legend>
                <label v-for="e in data.events" :key="e" class="flex items-center gap-2"><input v-model="form.events" type="checkbox" :value="e"> {{ t(evKey(e)) }} <code dir="ltr" class="text-xs text-muted">{{ e }}</code></label>
            </fieldset>
            <button class="btn btn-primary" :disabled="busy || !form.events.length">{{ t('whAdd') }}</button>
        </form>
        <p v-if="error" class="mt-2 text-sm text-red-600" role="alert">{{ error }}</p>

        <div v-if="secret" class="mt-4 rounded-[8px] border-2 border-brand p-4">
            <p class="text-sm font-medium">{{ t('whSecretOnce') }}</p>
            <div class="mt-2 flex items-center gap-2">
                <code dir="ltr" class="min-w-0 flex-1 truncate rounded bg-canvas px-2 py-1 text-sm">{{ secret }}</code>
                <button class="btn btn-ghost !min-h-0 !px-3 !py-1 text-sm" @click="copyText(secret)">{{ t('copy') }}</button>
            </div>
        </div>

        <ul v-if="data.webhooks.length" class="mt-5 space-y-3">
            <li v-for="w in data.webhooks" :key="w.id" class="rounded-[8px] border border-line p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <code dir="ltr" class="min-w-0 break-all text-sm">{{ w.url }}</code>
                    <span class="rounded-full px-2 py-0.5 text-xs" :class="w.active && !w.disabled_at ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">{{ w.active && !w.disabled_at ? t('whOn') : t('whOff') }}</span>
                </div>
                <p class="hint mt-1">{{ w.events.map((e) => t(evKey(e))).join(' · ') }}</p>
                <p v-if="w.disabled_at" class="mt-1 text-xs text-red-700">{{ t('whAutoOff') }}</p>
                <p class="hint mt-1">{{ t('whLast') }}: {{ fmt(w.last_delivery_at) }}<span v-if="w.last_status" dir="ltr"> · {{ w.last_status }}</span></p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button class="btn btn-ghost !min-h-0 !px-3 !py-1 text-sm" @click="ping(w)">{{ t('whPing') }}</button>
                    <button class="btn btn-ghost !min-h-0 !px-3 !py-1 text-sm" @click="toggle(w)">{{ w.active && !w.disabled_at ? t('whOff') : t('whOn') }}</button>
                    <button class="btn btn-ghost !min-h-0 !px-3 !py-1 text-sm" @click="rotate(w)">{{ t('whRotate') }}</button>
                    <button class="btn btn-danger !min-h-0 !px-3 !py-1 text-sm" @click="remove(w)">{{ t('delete') }}</button>
                </div>
                <details v-if="w.deliveries?.length" class="mt-3 text-sm">
                    <summary class="cursor-pointer text-muted">{{ t('whDeliveries') }}</summary>
                    <ul class="mt-2 space-y-1" dir="ltr">
                        <li v-for="d in w.deliveries" :key="d.id" class="flex justify-between gap-3 text-xs"><span>{{ d.event }}</span><span :class="d.error ? 'text-red-700' : 'text-green-700'">{{ d.status ?? d.error ?? '…' }} · ×{{ d.attempts }}</span></li>
                    </ul>
                </details>
            </li>
        </ul>
    </div>
</template>
