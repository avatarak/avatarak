<script setup>
import { ref, reactive, onMounted } from 'vue';
import { api, ApiError } from '../../api';
import { copyText } from '../../composables';
import { t } from '../../i18n';
import WebhooksSection from './WebhooksSection.vue';

defineEmits(['goto']);
const sites = ref([]);
const SCOPES = ['profiles:read', 'profiles:lookup'];
const form = reactive({ name: '', domain: '', scopes: [...SCOPES] });
const scKey = (s) => 'sc_' + s.replace(':', '_');
const newKey = ref('');
const error = ref('');
const origin = window.location.origin;

const load = async () => (sites.value = await api('GET', '/account/sites'));
onMounted(load);

async function create() {
    error.value = ''; newKey.value = '';
    try {
        const s = await api('POST', '/account/sites', form);
        newKey.value = s.api_key;
        form.name = form.domain = ''; form.scopes = [...SCOPES];
        await load();
    } catch (e) {
        if (!(e instanceof ApiError)) throw e;
        error.value = Object.values(e.errors)[0]?.[0] || e.message;
    }
}
async function remove(s) {
    if (!confirm(t('confirmDelete'))) return;
    await api('DELETE', `/account/sites/${s.id}`);
    await load();
}
const fmt = (d) => (d ? new Date(d).toLocaleString() : t('never'));
</script>

<template>
    <div>
        <a href="/docs" target="_blank" rel="noopener" class="btn btn-ghost mb-2 w-full">{{ t('docsLink') }} <Icon name="external" :size="16" /></a>
        <a href="/docs/api-explorer" target="_blank" rel="noopener" class="btn btn-ghost mb-5 w-full">{{ t('explorerLink') }} <Icon name="external" :size="16" /></a>
        <form class="grid gap-2" @submit.prevent="create">
            <input v-model="form.name" class="field" :placeholder="t('siteName')" :aria-label="t('siteName')" required>
            <input v-model="form.domain" dir="ltr" class="field" :placeholder="t('siteDomain')" :aria-label="t('siteDomain')" required>
            <fieldset class="grid gap-1 text-sm">
                <legend class="label">{{ t('scopesLabel') }}</legend>
                <label v-for="s in SCOPES" :key="s" class="flex items-center gap-2"><input v-model="form.scopes" type="checkbox" :value="s"> {{ t(scKey(s)) }} <code dir="ltr" class="text-xs text-muted">{{ s }}</code></label>
            </fieldset>
            <button class="btn btn-primary" :disabled="!form.scopes.length">{{ t('createKey') }}</button>
        </form>
        <p v-if="error" class="mt-2 text-sm text-red-600" role="alert">{{ error }}</p>

        <div v-if="newKey" class="mt-4 rounded-[8px] border-2 border-brand p-4">
            <p class="text-sm font-medium">{{ t('keyOnce') }}</p>
            <div class="mt-2 flex items-center gap-2">
                <code dir="ltr" class="min-w-0 flex-1 truncate rounded bg-canvas px-2 py-1 text-sm">{{ newKey }}</code>
                <button class="btn btn-ghost !min-h-0 !px-3 !py-1 text-sm" @click="copyText(newKey)">{{ t('copy') }}</button>
            </div>
        </div>

        <p v-if="!sites.length" class="mt-6 text-muted">{{ t('empty') }}</p>
        <ul v-else class="mt-6 space-y-3">
            <li v-for="s in sites" :key="s.id" class="flex items-center justify-between gap-3 rounded-[8px] border border-line p-4">
                <div class="min-w-0">
                    <div class="font-medium">{{ s.name }} <span dir="ltr" class="text-muted">· {{ s.domain }}</span></div>
                    <div class="hint mt-0.5"><span dir="ltr">{{ s.api_key_prefix }}…</span> · {{ t('lastUsed') }}: {{ fmt(s.last_used_at) }}</div>
                    <div class="hint mt-0.5" dir="ltr">{{ (s.scopes || []).join(' · ') }}</div>
                </div>
                <button class="btn btn-danger !min-h-0 !px-3 !py-1 text-sm" @click="remove(s)">{{ t('delete') }}</button>
            </li>
        </ul>

        <h3 class="mt-9 text-lg font-bold">{{ t('example') }}</h3>
        <pre dir="ltr" class="mt-2 overflow-x-auto rounded-[8px] bg-soft p-4 text-start text-xs leading-6">curl -H "Authorization: Bearer avk_…" \
  {{ origin }}/api/v1/profiles/&lt;sha256-of-email&gt;</pre>

        <WebhooksSection @goto="(s) => $emit('goto', s)" />
    </div>
</template>
