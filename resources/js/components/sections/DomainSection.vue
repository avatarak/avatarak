<script setup>
import { ref, onMounted } from 'vue';
import { api, ApiError } from '../../api';
import { toast } from '../../store';
import { t } from '../../i18n';
import { copyText } from '../../composables';
import Icon from '../Icon.vue';

const data = ref(null);
const host = ref('');
const busy = ref(false);
const error = ref('');
const result = ref({});          // per domain id: { pointed, message }

async function load() { data.value = await api('GET', '/account/domains'); }
onMounted(load);

async function add() {
    busy.value = true; error.value = '';
    try { await api('POST', '/account/domains', { host: host.value }); host.value = ''; await load(); } catch (e) { error.value = e instanceof ApiError ? (Object.values(e.errors || {})[0]?.[0] || e.message) : t('error'); } finally { busy.value = false; }
}
async function check(d) {
    busy.value = true;
    try {
        const r = await api('POST', `/account/domains/${d.id}/check`);
        result.value = { ...result.value, [d.id]: { pointed: r.pointed, notFound: !r.verified } };
        if (r.verified) toast(t('dmVerified'));
        await load();
    } finally { busy.value = false; }
}
async function remove(d) { if (!confirm(t('confirmDelete'))) return; await api('DELETE', `/account/domains/${d.id}`); await load(); }
</script>

<template>
    <div v-if="data" class="space-y-6">
        <div v-if="!data.allowed" class="rounded-[8px] border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
            {{ t('dmNeedPlan') }} <button type="button" class="font-semibold underline underline-offset-2" @click="$emit('goto', 'billing')">{{ t('dmUpgrade') }}</button>
        </div>
        <form v-else-if="data.domains.length < data.allowed" class="flex flex-col gap-2" novalidate @submit.prevent="add">
            <label class="label" for="dm-host">{{ t('dmHost') }}</label>
            <input id="dm-host" v-model="host" dir="ltr" class="field text-start" placeholder="example.com" inputmode="url" autocapitalize="none" spellcheck="false">
            <button class="btn btn-primary" :disabled="busy || !host">{{ t('dmAdd') }}</button>
            <p v-if="error" class="text-sm text-red-600" role="alert">{{ error }}</p>
        </form>

        <ul class="space-y-4">
            <li v-for="d in data.domains" :key="d.id" class="rounded-[8px] border border-line p-4">
                <div class="flex items-center justify-between gap-3">
                    <span dir="ltr" class="font-semibold">{{ d.host }}</span>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="d.verified ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900'">{{ d.verified ? t('dmVerified') : t('dmPending') }}</span>
                </div>
                <p v-if="d.verified && result[d.id]?.pointed !== false" class="mt-2 flex items-center gap-1.5 text-sm text-emerald-800"><Icon name="check" :size="15" /> {{ t('dmLive') }}</p>

                <template v-if="!d.verified || result[d.id]?.pointed === false">
                    <div v-if="!d.verified" class="mt-3 text-sm">
                        <p class="font-medium">{{ t('dmStep1') }}</p>
                        <dl class="mt-2 space-y-1.5 rounded-[6px] bg-soft p-3 text-xs" dir="ltr">
                            <div class="flex items-center justify-between gap-2"><span><b>{{ t('dmType') }}</b> TXT</span></div>
                            <div class="flex items-center justify-between gap-2"><span class="break-all"><b>{{ t('dmName') }}</b> {{ d.instructions.txt.name }}</span><button type="button" class="icon-btn shrink-0" :aria-label="t('copy')" @click="copyText(d.instructions.txt.name)"><Icon name="copy" :size="15" /></button></div>
                            <div class="flex items-center justify-between gap-2"><span class="break-all"><b>{{ t('dmValue') }}</b> {{ d.instructions.txt.value }}</span><button type="button" class="icon-btn shrink-0" :aria-label="t('copy')" @click="copyText(d.instructions.txt.value)"><Icon name="copy" :size="15" /></button></div>
                        </dl>
                    </div>
                    <div class="mt-3 text-sm">
                        <p class="font-medium">{{ t('dmStep2') }}</p>
                        <dl class="mt-2 space-y-1.5 rounded-[6px] bg-soft p-3 text-xs" dir="ltr">
                            <div><b>{{ t('dmType') }}</b> CNAME</div>
                            <div class="break-all"><b>{{ t('dmName') }}</b> {{ d.host }}</div>
                            <div class="flex items-center justify-between gap-2"><span class="break-all"><b>{{ t('dmValue') }}</b> {{ d.instructions.route.value }}</span><button type="button" class="icon-btn shrink-0" :aria-label="t('copy')" @click="copyText(d.instructions.route.value)"><Icon name="copy" :size="15" /></button></div>
                        </dl>
                    </div>
                </template>

                <p v-if="result[d.id]?.notFound" class="mt-3 text-sm text-amber-800" role="status">{{ t('dmNotFound') }}</p>
                <p v-else-if="result[d.id]?.pointed === false" class="mt-3 text-sm text-amber-800" role="status">{{ t('dmNotPointed') }}</p>
                <p v-else-if="result[d.id]?.pointed" class="mt-3 text-sm text-emerald-800" role="status">{{ t('dmPointed') }}</p>

                <div class="mt-4 flex gap-2">
                    <button type="button" class="btn btn-primary flex-1" :disabled="busy" @click="check(d)">{{ busy ? t('dmChecking') : t('dmCheck') }}</button>
                    <button type="button" class="btn btn-ghost !text-red-700" @click="remove(d)">{{ t('dmRemove') }}</button>
                </div>
            </li>
        </ul>
    </div>
    <p v-else class="py-10 text-center text-muted">{{ t('loading') }}</p>
</template>
