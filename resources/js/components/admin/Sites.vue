<script setup>
import { reactive, computed } from 'vue';
import { useList, adminApi, errorText, ago, fmtDate, fmtNum } from '../../admin/lib';
import { go } from '../../admin/store';
import { t } from '../../i18n';
import { toast } from '../../store';
import Icon from '../Icon.vue';
import Badge from './Badge.vue';
import Pager from './Pager.vue';
import ConfirmDialog from './ConfirmDialog.vue';

const list = useList('/sites', { status: '' });
const FILTERS = [['', 'a_sites_all'], ['active', 'a_sites_active'], ['idle', 'a_sites_idle'], ['disabled', 'a_sites_disabled']];

const dlg = reactive({ open: false, kind: '', site: null, busy: false, error: '' });
const ask = (kind, site) => Object.assign(dlg, { open: true, kind, site, busy: false, error: '' });
const cfg = computed(() => {
    const s = dlg.site; const d = s?.domain || '';
    return ({
        disable: { title: `${t('a_disable')}: ${d}`, body: t('a_disable_text'), label: t('a_disable'), danger: true },
        enable: { title: `${t('a_enable')}: ${d}`, label: t('a_enable') },
        limit: { title: `${t('a_edit_limit')}: ${d}`, body: t('a_limit_help') + ' ' + t('a_limit_help2'), inputLabel: t('a_limit'), inputValue: s?.rate_limit_override ? String(s.rate_limit) : '', inputType: 'number', label: t('a_save'), allowEmpty: true },
        delete: { title: `${t('a_delete_site')}: ${d}`, body: t('a_delete_site_text'), typed: d, label: t('a_delete_site'), danger: true },
    })[dlg.kind] || {};
});
async function run({ input }) {
    dlg.busy = true; dlg.error = '';
    try {
        const id = dlg.site.id;
        if (dlg.kind === 'delete') await adminApi('DELETE', `/sites/${id}`);
        else if (dlg.kind === 'limit') await adminApi('PATCH', `/sites/${id}`, { rate_limit_per_minute: input === '' ? null : Number(input) });
        else await adminApi('PATCH', `/sites/${id}`, { disabled: dlg.kind === 'disable' });
        dlg.open = false; toast(t('a_done')); await list.load();
    } catch (e) { dlg.error = errorText(e); } finally { dlg.busy = false; }
}
</script>

<template>
    <div class="space-y-4">
        <div class="relative">
            <Icon name="search" :size="18" class="pointer-events-none absolute inset-y-0 start-3 my-auto text-muted" />
            <input v-model="list.params.q" type="search" class="field !ps-10" :placeholder="t('a_search_sites')" :aria-label="t('a_search_sites')">
        </div>
        <div class="flex flex-wrap gap-1.5" role="group">
            <button v-for="[v, label] in FILTERS" :key="v" type="button" class="rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors"
                    :class="list.params.status === v ? 'bg-ink text-white' : 'bg-soft text-ink-2 hover:bg-line/60'" :aria-pressed="list.params.status === v" @click="list.params.status = v; list.params.page = 1">{{ t(label) }}</button>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[46rem] text-sm">
                    <thead class="bg-soft text-xs uppercase tracking-wide text-muted">
                        <tr><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_site') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_owner') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_limit') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_last_used') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_status') }}</th><th class="px-4 py-2.5"><span class="sr-only">{{ t('a_actions') }}</span></th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="s in list.rows" :key="s.id" class="hover:bg-soft">
                            <td class="px-4 py-2.5"><div class="font-medium" dir="ltr">{{ s.domain }}</div><div class="text-xs text-muted"><span dir="ltr">{{ s.name }} · {{ s.prefix }}…</span> · {{ fmtDate(s.created_at) }}</div></td>
                            <td class="px-4 py-2.5"><button type="button" dir="ltr" class="text-start text-brand hover:underline" @click="go('users', null, s.owner.id)">{{ s.owner.email }}</button></td>
                            <td class="px-4 py-2.5 tabular-nums">{{ fmtNum(s.rate_limit) }} <span v-if="s.rate_limit_override" class="text-xs text-amber-700">({{ t('a_limit_override') }})</span></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-muted">{{ s.last_used_at ? ago(s.last_used_at) : t('a_never') }}</td>
                            <td class="px-4 py-2.5"><div class="flex flex-wrap gap-1"><Badge v-if="s.disabled" tone="red">{{ t('a_disabled') }}</Badge><Badge v-else tone="green">{{ t('a_active') }}</Badge><Badge v-if="s.owner_suspended" tone="amber">{{ t('a_owner_suspended') }}</Badge></div></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-end">
                                <button type="button" class="icon-btn" :title="t('a_edit_limit')" :aria-label="t('a_edit_limit')" @click="ask('limit', s)"><Icon name="settings" :size="18" /></button>
                                <button type="button" class="icon-btn" :title="s.disabled ? t('a_enable') : t('a_disable')" :aria-label="s.disabled ? t('a_enable') : t('a_disable')" @click="ask(s.disabled ? 'enable' : 'disable', s)"><Icon :name="s.disabled ? 'check' : 'ban'" :size="18" /></button>
                                <button type="button" class="icon-btn text-red-700" :title="t('a_delete_site')" :aria-label="t('a_delete_site')" @click="ask('delete', s)"><Icon name="trash" :size="18" /></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="list.loading && !list.rows.length" class="px-4 py-10 text-center text-muted">{{ t('a_loading') }}</p>
            <div v-else-if="list.error" class="px-4 py-10 text-center"><p class="text-red-700">{{ list.error }}</p><button type="button" class="btn btn-ghost mt-3" @click="list.load">{{ t('a_retry') }}</button></div>
            <p v-else-if="!list.rows.length" class="px-4 py-10 text-center text-muted">{{ t('a_empty') }}</p>
            <div class="border-t border-line"><Pager :meta="list.meta" @page="(p) => (list.params.page = p)" /></div>
        </div>

        <ConfirmDialog :open="dlg.open" :title="cfg.title" :body="cfg.body" :confirm-label="cfg.label" :danger="cfg.danger" :busy="dlg.busy" :error="dlg.error" :typed="cfg.typed" :typed-label="t('a_type_domain')"
                       :input-label="cfg.inputLabel" :allow-empty="!!cfg.allowEmpty" :input-value="cfg.inputValue || ''" :input-type="cfg.inputType || 'text'" @close="dlg.open = false" @confirm="run" />
    </div>
</template>
