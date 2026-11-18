<script setup>
import { reactive } from 'vue';
import { useList, adminApi, errorText, fmtDate } from '../../admin/lib';
import { go } from '../../admin/store';
import { t } from '../../i18n';
import { toast } from '../../store';
import Icon from '../Icon.vue';
import SocialIcon from '../SocialIcon.vue';
import Badge from './Badge.vue';
import Pager from './Pager.vue';
import ConfirmDialog from './ConfirmDialog.vue';

const list = useList('/verified', { network: '' });
const NETS = [['', 'a_all'], ['website', 'vWebsite'], ['github', 'vGithub'], ['telegram', 'vTelegram']];
const dlg = reactive({ open: false, row: null, busy: false, error: '' });

async function revoke() {
    dlg.busy = true; dlg.error = '';
    try { await adminApi('DELETE', `/verified/${dlg.row.id}`); dlg.open = false; toast(t('a_done')); await list.load(); } catch (e) { dlg.error = errorText(e); } finally { dlg.busy = false; }
}
</script>

<template>
    <div class="space-y-4">
        <div class="relative">
            <Icon name="search" :size="18" class="pointer-events-none absolute inset-y-0 start-3 my-auto text-muted" />
            <input v-model="list.params.q" type="search" class="field !ps-10" :placeholder="t('a_search_verified')" :aria-label="t('a_search_verified')">
        </div>
        <div class="flex flex-wrap gap-1.5" role="group">
            <button v-for="[v, label] in NETS" :key="v" type="button" class="rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors"
                    :class="list.params.network === v ? 'bg-ink text-white' : 'bg-soft text-ink-2 hover:bg-line/60'" :aria-pressed="list.params.network === v" @click="list.params.network = v; list.params.page = 1">{{ t(label) }}</button>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead class="bg-soft text-xs uppercase tracking-wide text-muted">
                        <tr><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_network') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_identifier') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_owner') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_method') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_verified_at') }}</th><th class="px-4 py-2.5"><span class="sr-only">{{ t('a_actions') }}</span></th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="a in list.rows" :key="a.id" class="hover:bg-soft">
                            <td class="px-4 py-2.5"><span class="inline-flex items-center gap-2"><SocialIcon v-if="a.network !== 'website'" :name="a.network" :size="16" /><Icon v-else name="globe" :size="16" /> {{ a.network }}</span></td>
                            <td class="px-4 py-2.5"><a :href="a.url" target="_blank" rel="noopener" dir="ltr" class="text-brand hover:underline">{{ a.identifier }}</a></td>
                            <td class="px-4 py-2.5"><button type="button" dir="ltr" class="text-start text-brand hover:underline" @click="go('users', null, a.user.id)">{{ a.user.email }}</button></td>
                            <td class="px-4 py-2.5"><Badge>{{ a.method }}</Badge></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-muted">{{ fmtDate(a.verified_at) }}</td>
                            <td class="px-4 py-2.5 text-end"><button type="button" class="icon-btn text-red-700" :title="t('a_revoke')" :aria-label="t('a_revoke')" @click="Object.assign(dlg, { open: true, row: a, busy: false, error: '' })"><Icon name="trash" :size="18" /></button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="list.loading && !list.rows.length" class="px-4 py-10 text-center text-muted">{{ t('a_loading') }}</p>
            <div v-else-if="list.error" class="px-4 py-10 text-center"><p class="text-red-700">{{ list.error }}</p><button type="button" class="btn btn-ghost mt-3" @click="list.load">{{ t('a_retry') }}</button></div>
            <p v-else-if="!list.rows.length" class="px-4 py-10 text-center text-muted">{{ t('a_empty') }}</p>
            <div class="border-t border-line"><Pager :meta="list.meta" @page="(p) => (list.params.page = p)" /></div>
        </div>

        <ConfirmDialog :open="dlg.open" :title="`${t('a_revoke')}: ${dlg.row?.identifier || ''}`" :body="t('a_revoke_text')" :confirm-label="t('a_revoke')" danger :busy="dlg.busy" :error="dlg.error" @close="dlg.open = false" @confirm="revoke" />
    </div>
</template>
