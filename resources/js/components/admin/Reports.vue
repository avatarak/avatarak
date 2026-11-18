<script setup>
import { ref, reactive, computed } from 'vue';
import { useList, adminApi, errorText, fmtDateTime, ago } from '../../admin/lib';
import { adm, go } from '../../admin/store';
import { t } from '../../i18n';
import { toast } from '../../store';
import Icon from '../Icon.vue';
import Badge from './Badge.vue';
import Pager from './Pager.vue';
import UserAvatar from './UserAvatar.vue';
import ConfirmDialog from './ConfirmDialog.vue';

const list = useList('/reports', { status: 'open', reason: '' });
const REASONS = ['spam', 'impersonation', 'inappropriate', 'illegal', 'other'];
const counts = computed(() => list.extra?.counts || { open: 0, actioned: 0, dismissed: 0 });

const dlg = reactive({ open: false, kind: '', report: null, busy: false, error: '' });
const ask = (kind, report) => Object.assign(dlg, { open: true, kind, report, busy: false, error: '' });

async function run({ note, checks }) {
    dlg.busy = true; dlg.error = '';
    try {
        if (dlg.kind === 'reopen') await adminApi('POST', `/reports/${dlg.report.id}/reopen`);
        else await adminApi('POST', `/reports/${dlg.report.id}/resolve`, { status: dlg.kind === 'dismiss' ? 'dismissed' : 'actioned', note, all_for_profile: !!checks.all });
        dlg.open = false; toast(t('a_done'));
        await list.load();
        if (adm.counts) adm.counts.reports_open = list.extra?.counts?.open ?? adm.counts.reports_open;
    } catch (e) { dlg.error = errorText(e); } finally { dlg.busy = false; }
}
const cfg = computed(() => ({
    dismiss: { title: t('a_rp_dismiss'), label: t('a_rp_dismiss'), checks: [{ key: 'all', label: t('a_rp_all') }] },
    action: { title: t('a_rp_action'), label: t('a_rp_action'), checks: [{ key: 'all', label: t('a_rp_all') }] },
    reopen: { title: t('a_rp_reopen'), label: t('a_rp_reopen'), checks: [] },
}[dlg.kind] || {}));
const TONE = { spam: 'amber', impersonation: 'violet', inappropriate: 'red', illegal: 'red', other: 'gray' };
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex gap-1 rounded-[8px] bg-soft p-1" role="group">
                <button v-for="s in ['open', 'actioned', 'dismissed']" :key="s" type="button" class="rounded-[6px] px-3.5 py-1.5 text-sm font-medium"
                        :class="list.params.status === s ? 'bg-white shadow-card' : 'text-muted hover:text-ink'" :aria-pressed="list.params.status === s" @click="list.params.status = s; list.params.page = 1">
                    {{ t('a_rp_' + s) }} <span class="tabular-nums text-muted">{{ counts[s] }}</span>
                </button>
            </div>
            <select v-model="list.params.reason" class="field !w-auto" :aria-label="t('a_rp_reason')" @change="list.params.page = 1">
                <option value="">{{ t('a_rp_all_reasons') }}</option>
                <option v-for="r in REASONS" :key="r" :value="r">{{ t('rp_' + r) }}</option>
            </select>
        </div>

        <p v-if="list.loading && !list.rows.length" class="py-12 text-center text-muted">{{ t('a_loading') }}</p>
        <div v-else-if="list.error" class="card p-8 text-center"><p class="text-red-700">{{ list.error }}</p><button type="button" class="btn btn-ghost mt-3" @click="list.load">{{ t('a_retry') }}</button></div>
        <div v-else-if="!list.rows.length" class="card p-12 text-center text-muted"><Icon name="check" :size="28" class="mx-auto mb-2 text-emerald-600" />{{ t('a_rp_empty') }}</div>

        <ul v-else class="space-y-3">
            <li v-for="r in list.rows" :key="r.id" class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <UserAvatar v-if="r.profile" :user="{ ...r.profile, id: r.profile.user_id, suspended: r.profile.suspended }" :size="44" />
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-bold">{{ r.profile?.display }}</span>
                                <span dir="ltr" class="text-sm text-muted">@{{ r.profile?.username }}</span>
                                <Badge v-if="r.profile?.suspended" tone="red">{{ t('a_suspended') }}</Badge>
                            </div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-muted">
                                <Badge :tone="TONE[r.reason]">{{ t('rp_' + r.reason) }}</Badge>
                                <span :title="fmtDateTime(r.created_at)">{{ ago(r.created_at) }}</span>
                                <span v-if="r.open_for_profile > 1 && r.status === 'open'" class="font-semibold text-amber-800">+{{ r.open_for_profile - 1 }} {{ t('a_rp_more') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a v-if="r.profile && !r.profile.suspended" :href="`/${r.profile.username}`" target="_blank" rel="noopener" class="btn btn-ghost !py-1.5 text-sm"><Icon name="external" :size="16" /> {{ t('a_view_profile') }}</a>
                        <button v-if="r.profile" type="button" class="btn btn-ghost !py-1.5 text-sm" @click="go('users', null, r.profile.user_id)"><Icon name="user" :size="16" /> {{ t('a_rp_review') }}</button>
                    </div>
                </div>

                <blockquote v-if="r.details" class="mt-3 whitespace-pre-line break-words rounded-[8px] bg-soft px-4 py-3 text-sm">{{ r.details }}</blockquote>

                <div v-if="r.status === 'open'" class="mt-4 flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary !py-1.5 text-sm" @click="ask('action', r)"><Icon name="check" :size="16" /> {{ t('a_rp_action') }}</button>
                    <button type="button" class="btn btn-ghost !py-1.5 text-sm" @click="ask('dismiss', r)">{{ t('a_rp_dismiss') }}</button>
                </div>
                <div v-else class="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm text-muted">
                    <span><Badge :tone="r.status === 'actioned' ? 'green' : 'gray'">{{ t('a_rp_' + r.status) }}</Badge> · {{ t('a_rp_handled_by') }} {{ r.handled_by || '—' }} · {{ ago(r.handled_at) }}<template v-if="r.note"> · “{{ r.note }}”</template></span>
                    <button type="button" class="font-semibold text-brand hover:underline" @click="ask('reopen', r)">{{ t('a_rp_reopen') }}</button>
                </div>
            </li>
        </ul>
        <div class="card"><Pager :meta="list.meta" @page="(p) => (list.params.page = p)" /></div>

        <ConfirmDialog :open="dlg.open" :title="cfg.title" :confirm-label="cfg.label" :busy="dlg.busy" :error="dlg.error" :checks="cfg.checks || []"
                       :note-label="dlg.kind === 'reopen' ? '' : t('a_rp_note')" @close="dlg.open = false" @confirm="run" />
    </div>
</template>
