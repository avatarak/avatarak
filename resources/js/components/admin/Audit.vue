<script setup>
import { ref } from 'vue';
import { useList, fmtDateTime, ago } from '../../admin/lib';
import { t } from '../../i18n';
import Icon from '../Icon.vue';
import Badge from './Badge.vue';
import Pager from './Pager.vue';

const list = useList('/audit', { action: '' });
const GROUPS = [['', 'a_all'], ['user.', 'a_nav_users'], ['report.', 'a_nav_reports'], ['site.', 'a_nav_sites'], ['verified.', 'a_nav_verified'], ['settings.', 'a_nav_settings'], ['announcement.', 'a_ann_title'], ['export.', 'a_export_csv']];
const openRow = ref(null);
const TONE = (a) => (/delete|suspend$|disable|revoke|demote/.test(a) ? 'red' : /promote|unsuspend|enable/.test(a) ? 'green' : 'blue');
const label = (a) => t('act_' + a.replace('.', '_'));
</script>

<template>
    <div class="space-y-4">
        <div class="relative">
            <Icon name="search" :size="18" class="pointer-events-none absolute inset-y-0 start-3 my-auto text-muted" />
            <input v-model="list.params.q" type="search" class="field !ps-10" :placeholder="t('a_search_audit')" :aria-label="t('a_search_audit')">
        </div>
        <div class="flex flex-wrap gap-1.5" role="group">
            <button v-for="[v, l] in GROUPS" :key="v" type="button" class="rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors"
                    :class="list.params.action === v ? 'bg-ink text-white' : 'bg-soft text-ink-2 hover:bg-line/60'" :aria-pressed="list.params.action === v" @click="list.params.action = v; list.params.page = 1">{{ t(l) }}</button>
        </div>
        <p class="hint flex items-center gap-1.5"><Icon name="lock" :size="14" /> {{ t('a_audit_note') }}</p>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[42rem] text-sm">
                    <thead class="bg-soft text-xs uppercase tracking-wide text-muted">
                        <tr><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_when') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_who') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_what') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_target') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <template v-for="l in list.rows" :key="l.id">
                            <tr class="cursor-pointer hover:bg-soft" tabindex="0" @click="openRow = openRow === l.id ? null : l.id" @keydown.enter="openRow = openRow === l.id ? null : l.id">
                                <td class="whitespace-nowrap px-4 py-2.5 text-muted" :title="fmtDateTime(l.at)">{{ ago(l.at) }}</td>
                                <td class="px-4 py-2.5" dir="ltr">{{ l.admin }}</td>
                                <td class="px-4 py-2.5"><Badge :tone="TONE(l.action)">{{ label(l.action) }}</Badge></td>
                                <td class="px-4 py-2.5" dir="ltr">{{ l.target || '—' }}</td>
                            </tr>
                            <tr v-if="openRow === l.id" class="bg-soft/60">
                                <td colspan="4" class="px-4 py-3"><div class="text-xs text-muted">{{ t('a_details') }} · {{ fmtDateTime(l.at) }} · <span dir="ltr">{{ l.action }}</span></div><pre dir="ltr" class="mt-1 overflow-x-auto whitespace-pre-wrap text-start text-xs">{{ l.meta ? JSON.stringify(l.meta, null, 2) : '—' }}</pre></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p v-if="list.loading && !list.rows.length" class="px-4 py-10 text-center text-muted">{{ t('a_loading') }}</p>
            <div v-else-if="list.error" class="px-4 py-10 text-center"><p class="text-red-700">{{ list.error }}</p><button type="button" class="btn btn-ghost mt-3" @click="list.load">{{ t('a_retry') }}</button></div>
            <p v-else-if="!list.rows.length" class="px-4 py-10 text-center text-muted">{{ t('a_empty') }}</p>
            <div class="border-t border-line"><Pager :meta="list.meta" @page="(p) => (list.params.page = p)" /></div>
        </div>
    </div>
</template>
