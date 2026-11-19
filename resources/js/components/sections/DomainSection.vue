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
const showDns = ref({});
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
const isLive = (d) => d.verified && (result.value[d.id]?.pointed ?? d.pointed) !== false;
const openUrl = (d) => `https://${d.host}`;
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
            <li v-for="d in data.domains" :key="d.id" :class="isLive(d) ? 'overflow-hidden rounded-[14px] border border-emerald-200 bg-white shadow-sm' : 'rounded-[8px] border border-line p-4'">
                <!-- live: verified and pointing at us -->
                <template v-if="isLive(d)">
                    <div class="bg-gradient-to-br from-emerald-50 via-white to-white p-5">
                        <div class="flex items-center gap-3.5">
                            <span class="grid size-12 shrink-0 place-items-center rounded-full bg-emerald-600 text-white shadow-[0_0_0_6px_rgb(16_185_129/0.14)]"><Icon name="globe" :size="24" /></span>
                            <div class="min-w-0">
                                <p class="flex items-center gap-1.5 text-xs font-semibold text-emerald-800">
                                    <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-500 opacity-60 motion-reduce:animate-none"></span><span class="relative inline-flex size-2 rounded-full bg-emerald-600"></span></span>
                                    {{ t('dmLiveTitle') }}
                                </p>
                                <a :href="openUrl(d)" target="_blank" rel="noopener" dir="ltr" class="mt-0.5 block truncate text-start text-xl font-bold tracking-tight hover:underline">{{ d.host }}</a>
                            </div>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-muted">{{ t('dmLive') }}</p>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs font-medium">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-800 ring-1 ring-emerald-200"><Icon name="check" :size="14" /> {{ t('dmVerified') }}</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-800 ring-1 ring-emerald-200"><Icon name="lock" :size="14" /> {{ t('dmHttps') }}</span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a :href="openUrl(d)" target="_blank" rel="noopener" class="btn btn-primary flex-1"><Icon name="external" :size="16" /> {{ t('dmOpen') }}</a>
                            <button type="button" class="btn btn-ghost" @click="copyText(openUrl(d))"><Icon name="copy" :size="16" /> {{ t('dmCopyLink') }}</button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-3 border-t border-emerald-100 bg-white px-5 py-3 text-sm">
                        <button type="button" class="font-medium text-muted underline-offset-2 hover:underline" @click="showDns[d.id] = !showDns[d.id]">{{ t('dmDnsDetails') }}</button>
                        <button type="button" class="font-medium text-red-700 underline-offset-2 hover:underline" @click="remove(d)">{{ t('dmRemove') }}</button>
                    </div>
                    <dl v-if="showDns[d.id]" class="mx-5 mb-5 space-y-1.5 rounded-[6px] bg-soft p-3 text-xs" dir="ltr">
                        <div><b>{{ t('dmType') }}</b> CNAME</div>
                        <div class="break-all"><b>{{ t('dmName') }}</b> {{ d.host }}</div>
                        <div class="break-all"><b>{{ t('dmValue') }}</b> {{ d.instructions.route.value }}</div>
                    </dl>
                </template>

                <!-- not finished yet -->
                <template v-else>
                    <div class="flex items-center justify-between gap-3">
                        <span dir="ltr" class="font-semibold">{{ d.host }}</span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="d.verified ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900'">{{ d.verified ? t('dmVerified') : t('dmPending') }}</span>
                    </div>
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
                    <p v-if="result[d.id]?.notFound" class="mt-3 text-sm text-amber-800" role="status">{{ t('dmNotFound') }}</p>
                    <p v-else-if="result[d.id]?.pointed === false" class="mt-3 text-sm text-amber-800" role="status">{{ t('dmNotPointed') }}</p>
                    <div class="mt-4 flex gap-2">
                        <button type="button" class="btn btn-primary flex-1" :disabled="busy" @click="check(d)">{{ busy ? t('dmChecking') : t('dmCheck') }}</button>
                        <button type="button" class="btn btn-ghost !text-red-700" @click="remove(d)">{{ t('dmRemove') }}</button>
                    </div>
                </template>
            </li>
        </ul>
    </div>
    <p v-else class="py-10 text-center text-muted">{{ t('loading') }}</p>
</template>
