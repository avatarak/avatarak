<script setup>
import { computed } from 'vue';
import { useList, fmtDateTime, fmtNum } from '../../admin/lib';
import { go } from '../../admin/store';
import { t } from '../../i18n';
import Icon from '../Icon.vue';
import Badge from './Badge.vue';
import Pager from './Pager.vue';

const list = useList('/payments', { status: '' });
const FILTERS = [['', 'a_pay_all'], ['paid', 'a_pay_paid'], ['pending', 'a_pay_pending'], ['failed', 'a_pay_failed']];
const totals = computed(() => list.extra?.totals || { paid_amount: 0, paid_count: 0 });
const TONE = { paid: 'green', failed: 'red', pending: 'amber' };
</script>

<template>
    <div class="space-y-4">
        <div class="card flex flex-wrap items-baseline gap-x-6 gap-y-1 px-5 py-4">
            <div><span class="text-sm text-muted">{{ t('a_pay_total') }}</span> <span class="text-2xl font-bold tabular-nums">{{ fmtNum(totals.paid_amount) }}</span></div>
            <div class="text-sm text-muted">{{ fmtNum(totals.paid_count) }} {{ t('a_pay_paid') }}</div>
        </div>
        <div class="relative">
            <Icon name="search" :size="18" class="pointer-events-none absolute inset-y-0 start-3 my-auto text-muted" />
            <input v-model="list.params.q" type="search" class="field !ps-10" :placeholder="t('a_search_payments')" :aria-label="t('a_search_payments')">
        </div>
        <div class="flex flex-wrap gap-1.5" role="group">
            <button v-for="[v, l] in FILTERS" :key="v" type="button" class="rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors"
                    :class="list.params.status === v ? 'bg-ink text-white' : 'bg-soft text-ink-2 hover:bg-line/60'" :aria-pressed="list.params.status === v" @click="list.params.status = v; list.params.page = 1">{{ t(l) }}</button>
        </div>
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[42rem] text-sm">
                    <thead class="bg-soft text-xs uppercase tracking-wide text-muted">
                        <tr><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_when') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_user') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_plan') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_pay_amount') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_pay_status') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_pay_ref') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="p in list.rows" :key="p.id" class="hover:bg-soft">
                            <td class="whitespace-nowrap px-4 py-2.5 text-muted">{{ fmtDateTime(p.paid_at || p.created_at) }}</td>
                            <td class="px-4 py-2.5"><button type="button" dir="ltr" class="text-start text-brand hover:underline" @click="go('users', null, p.user.id)">{{ p.user.email }}</button></td>
                            <td class="px-4 py-2.5">{{ p.plan }} · {{ p.period }}</td>
                            <td class="px-4 py-2.5 font-medium tabular-nums">{{ fmtNum(p.amount) }} <span class="text-xs text-muted">{{ p.currency }}</span></td>
                            <td class="px-4 py-2.5"><Badge :tone="TONE[p.status]">{{ p.status }}</Badge></td>
                            <td dir="ltr" class="px-4 py-2.5 text-start text-xs text-muted">{{ p.ref_id || '—' }} <span v-if="p.gateway === 'fake'" class="text-amber-700">(test)</span></td>
                        </tr>
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
