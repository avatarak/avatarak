<script setup>
import { ref, onMounted } from 'vue';
import { api } from '../../api';
import { toast } from '../../store';
import { t, i18n } from '../../i18n';
import Icon from '../Icon.vue';

const supported = ref(true);
const sessions = ref([]);
const loaded = ref(false);

async function load() {
    const r = await api('GET', '/account/sessions');
    supported.value = r.supported; sessions.value = r.sessions; loaded.value = true;
}
onMounted(load);

async function revoke(s) { await api('DELETE', `/account/sessions/${s.id}`); toast(t('ssRevoked')); await load(); }
async function revokeOthers() { await api('DELETE', '/account/sessions'); toast(t('ssRevoked')); await load(); }
const when = (iso) => new Date(iso).toLocaleString(i18n.locale === 'fa' ? 'fa-IR' : 'en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
const others = () => sessions.value.filter((s) => !s.current).length;
</script>

<template>
    <section v-if="loaded && supported" class="rounded-[8px] border border-line p-4">
        <h3 class="flex items-center gap-2 font-bold"><Icon name="clock" :size="20" /> {{ t('ssTitle') }}</h3>
        <p class="hint mt-2">{{ t('ssIntro') }}</p>
        <ul class="mt-3 divide-y divide-line">
            <li v-for="s in sessions" :key="s.id" class="flex items-center justify-between gap-3 py-2.5 text-sm">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 font-medium">
                        {{ [s.browser, s.os].filter(Boolean).join(' · ') || t('ssUnknown') }}
                        <span v-if="s.current" class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800">{{ t('ssCurrent') }}</span>
                    </div>
                    <div class="truncate text-xs text-muted"><span dir="ltr">{{ s.ip }}</span> · {{ when(s.last_active) }}</div>
                </div>
                <button v-if="!s.current" type="button" class="btn btn-ghost !px-3 !py-1.5 text-sm" @click="revoke(s)">{{ t('ssRevoke') }}</button>
            </li>
        </ul>
        <button v-if="others() > 0" type="button" class="btn btn-ghost mt-3 w-full" @click="revokeOthers">{{ t('ssRevokeOthers') }}</button>
    </section>
</template>
