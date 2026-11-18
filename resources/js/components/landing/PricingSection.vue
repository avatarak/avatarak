<script setup>
import { ref, computed, onMounted } from 'vue';
import { t, i18n } from '../../i18n';
import Icon from '../Icon.vue';

defineEmits(['start']);
const data = ref(null);
const period = ref('month');
onMounted(async () => { try { data.value = await (await fetch('/pricing.json')).json(); } catch { /* the section just stays hidden */ } });

const lang = computed(() => (i18n.locale === 'fa' ? 'fa' : 'en'));
const nf = computed(() => new Intl.NumberFormat(i18n.locale === 'fa' ? 'fa-IR' : 'en-US'));
const feats = (p) => [
    `${nf.value.format(p.limits.sites)} ${t('plSites')}`, `${nf.value.format(p.limits.rate_limit)} ${t('plRate')}`,
    `${nf.value.format(p.limits.gallery)} ${t('plGallery')}`,
    ...(p.limits.webhooks ? [`${nf.value.format(p.limits.webhooks)} ${t('plWebhooks')}`] : []),
    ...(p.features.custom_domain ? [t('plDomain')] : []), ...(p.features.white_label ? [t('plWhite')] : []),
];
</script>

<template>
    <section v-if="data" id="pricing" class="bg-soft px-5 py-20">
        <div class="mx-auto max-w-[1060px]">
            <h2 class="text-center text-3xl font-bold sm:text-4xl">{{ t('prTitle') }}</h2>
            <p class="mt-3 text-center text-lg text-muted">{{ t('prSub') }}</p>
            <div class="mx-auto mt-8 flex w-fit gap-1 rounded-[8px] bg-white p-1 shadow-card" role="group">
                <button v-for="p in ['month', 'year']" :key="p" type="button" class="rounded-[6px] px-4 py-1.5 text-sm font-medium" :class="period === p ? 'bg-ink text-white' : 'text-muted hover:text-ink'" :aria-pressed="period === p" @click="period = p">
                    {{ t(p === 'month' ? 'plMonth' : 'plYear') }}
                </button>
            </div>
            <div class="mt-10 grid gap-5 md:grid-cols-3">
                <article v-for="p in data.plans" :key="p.key" class="card flex flex-col p-7" :class="p.key === 'pro' ? 'ring-2 ring-brand' : ''">
                    <h3 class="text-xl font-bold">{{ p.name[lang] }}</h3>
                    <p class="mt-1 text-sm text-muted">{{ p.tagline[lang] }}</p>
                    <div class="mt-5 text-3xl font-bold">{{ p.price[period] ? nf.format(p.price[period]) : t('plFree') }}<span v-if="p.price[period]" class="ms-2 text-sm font-normal text-muted">{{ t('plToman') }} {{ t(period === 'month' ? 'plPerMonth' : 'plPerYear') }}</span></div>
                    <ul class="mt-5 flex-1 space-y-2 text-sm">
                        <li v-for="f in feats(p)" :key="f" class="flex items-center gap-2"><Icon name="check" :size="16" class="text-emerald-600" /> {{ f }}</li>
                    </ul>
                    <button type="button" class="btn mt-6 w-full" :class="p.key === 'pro' ? 'btn-primary' : 'btn-ghost'" @click="$emit('start')">{{ t('start') }}</button>
                </article>
            </div>
        </div>
    </section>
</template>
