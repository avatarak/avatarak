<script setup>
import { ref, computed, onMounted } from 'vue';
import { api, ApiError } from '../../api';
import { toast } from '../../store';
import { t, i18n } from '../../i18n';
import Icon from '../Icon.vue';

const data = ref(null);
const period = ref('month');
const busy = ref('');
const error = ref('');

async function load() { data.value = await api('GET', '/account/billing'); }
onMounted(load);

const nf = computed(() => new Intl.NumberFormat(i18n.locale === 'fa' ? 'fa-IR' : 'en-US'));
const lang = computed(() => (i18n.locale === 'fa' ? 'fa' : 'en'));
const money = (n) => `${nf.value.format(n)} ${t('plToman')}`;
const plans = computed(() => data.value?.plans || []);
const current = computed(() => data.value?.current?.plan || 'free');
const rank = (k) => plans.value.find((p) => p.key === k)?.rank ?? 0;
const until = computed(() => (data.value?.current?.ends_at ? new Date(data.value.current.ends_at).toLocaleDateString(i18n.locale === 'fa' ? 'fa-IR' : 'en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : ''));
const fmtDate = (iso) => (iso ? new Date(iso).toLocaleDateString(i18n.locale === 'fa' ? 'fa-IR' : 'en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '');
const nameOf = (k) => plans.value.find((p) => p.key === k)?.name?.[lang.value] || k;

async function buy(plan) {
    busy.value = plan; error.value = '';
    try {
        const r = await api('POST', '/account/billing/checkout', { plan, period: period.value });
        toast(t('plRedirect'));
        window.location.href = r.url;
    } catch (e) { error.value = e instanceof ApiError ? e.message : t('error'); busy.value = ''; }
}
const features = (p) => [
    [`${nf.value.format(p.limits.sites)} ${t('plSites')}`, true],
    [`${nf.value.format(p.limits.rate_limit)} ${t('plRate')}`, true],
    [`${nf.value.format(p.limits.gallery)} ${t('plGallery')}`, true],
    [`${nf.value.format(p.limits.sections)} ${t('plSections')}`, true],
    ...(p.limits.webhooks ? [[`${nf.value.format(p.limits.webhooks)} ${t('plWebhooks')}`, true]] : []),
    [t('plDomain'), p.features.custom_domain],
    [t('plWhite'), p.features.white_label],
];
const statusLabel = (s) => ({ paid: t('plPaid'), failed: t('plFailed'), pending: t('plPending') }[s] || s);
</script>

<template>
    <div v-if="data" class="space-y-6">
        <section class="rounded-[8px] border border-line p-4">
            <div class="text-sm text-muted">{{ t('plCurrent') }}</div>
            <div class="mt-1 flex flex-wrap items-baseline gap-x-3 text-2xl font-bold">
                {{ nameOf(current) }}
                <span v-if="until" class="text-sm font-normal text-muted">{{ t('plUntil') }} {{ until }}<template v-if="data.current.source === 'admin'"> · {{ t('plManual') }}</template></span>
            </div>
            <div class="hint mt-2">{{ t('plUsage') }}: {{ nf.format(data.usage.sites) }} / {{ nf.format(plans.find((p) => p.key === current)?.limits.sites ?? 0) }}</div>
        </section>

        <div class="flex gap-1 rounded-[8px] bg-soft p-1" role="group">
            <button v-for="p in ['month', 'year']" :key="p" type="button" class="flex-1 rounded-[6px] px-3 py-1.5 text-sm font-medium" :class="period === p ? 'bg-white shadow-card' : 'text-muted hover:text-ink'" :aria-pressed="period === p" @click="period = p">
                {{ t(p === 'month' ? 'plMonth' : 'plYear') }}<span v-if="p === 'year'" class="ms-1.5 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800">{{ t('plYearSave') }}</span>
            </button>
        </div>

        <ul class="space-y-3">
            <li v-for="p in plans" :key="p.key" class="rounded-[8px] border p-4" :class="p.key === current ? 'border-brand bg-brand/5' : 'border-line'">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold">{{ p.name[lang] }} <span v-if="p.key === current" class="ms-1 rounded-full bg-brand px-2 py-0.5 align-middle text-xs font-semibold text-white">{{ t('plCurrentBadge') }}</span></h3>
                        <p class="hint">{{ p.tagline[lang] }}</p>
                    </div>
                    <div class="text-end">
                        <div class="text-xl font-bold">{{ p.price[period] ? money(p.price[period]) : t('plFree') }}</div>
                        <div v-if="p.price[period]" class="text-xs text-muted">{{ t(period === 'month' ? 'plPerMonth' : 'plPerYear') }}</div>
                    </div>
                </div>
                <ul class="mt-3 space-y-1.5 text-sm">
                    <li v-for="[label, ok] in features(p)" :key="label" class="flex items-center gap-2" :class="ok ? '' : 'text-muted line-through'"><Icon :name="ok ? 'check' : 'close'" :size="15" :class="ok ? 'text-emerald-600' : ''" /> {{ label }}</li>
                </ul>
                <template v-if="p.key !== 'free'">
                    <button v-if="data.enabled" type="button" class="btn mt-3 w-full" :class="p.key === current ? 'btn-ghost' : 'btn-primary'" :disabled="!!busy" @click="buy(p.key)">
                        {{ busy === p.key ? t('plRedirect') : (p.key === current ? t('plRenew') : (rank(p.key) > rank(current) ? t('plUpgrade') : t('plRenew'))) }}
                    </button>
                    <p v-else class="hint mt-3">{{ t('plContact') }} <a :href="`mailto:${data.contact_email}`" dir="ltr" class="font-semibold text-brand hover:underline">{{ data.contact_email }}</a></p>
                </template>
            </li>
        </ul>
        <p v-if="error" class="text-sm text-red-600" role="alert">{{ error }}</p>

        <section>
            <h3 class="font-bold">{{ t('plHistory') }}</h3>
            <p v-if="!data.payments.length" class="hint mt-2">{{ t('plNoPayments') }}</p>
            <ul v-else class="mt-2 divide-y divide-line text-sm">
                <li v-for="p in data.payments" :key="p.id" class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                    <span>{{ nameOf(p.plan) }} · {{ t(p.period === 'month' ? 'plMonth' : 'plYear') }}<span class="block text-xs text-muted">{{ fmtDate(p.paid_at || p.created_at) }}<template v-if="p.ref_id"> · {{ t('plRef') }} <span dir="ltr">{{ p.ref_id }}</span></template></span></span>
                    <span class="text-end"><span class="block font-semibold">{{ money(p.amount) }}</span><span class="text-xs" :class="p.status === 'paid' ? 'text-emerald-700' : 'text-muted'">{{ statusLabel(p.status) }}</span></span>
                </li>
            </ul>
        </section>
    </div>
    <p v-else class="py-10 text-center text-muted">{{ t('loading') }}</p>
</template>
