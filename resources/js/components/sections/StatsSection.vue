<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { api } from '../../api';
import { t, i18n } from '../../i18n';

const METRICS = [['avatar', 'stAvatar'], ['profile', 'stProfile'], ['export', 'stExport'], ['api', 'stApi']];
const days = ref(30);
const metric = ref('avatar');
const data = ref(null);
const loading = ref(true);

async function load() {
    loading.value = true;
    try { data.value = await api('GET', `/account/stats?days=${days.value}`); } finally { loading.value = false; }
}
onMounted(load);
watch(days, load);

const nf = computed(() => new Intl.NumberFormat(i18n.locale === 'fa' ? 'fa-IR' : 'en-US'));
const fmt = (n) => nf.value.format(n);
const bars = computed(() => {
    if (!data.value) return [];
    const max = Math.max(1, ...data.value.series.map((d) => d[metric.value]));
    const n = data.value.series.length;
    const w = 600 / n;
    return data.value.series.map((d, i) => {
        const v = d[metric.value];
        const h = v === 0 ? 2 : Math.max(4, (v / max) * 130);
        return { x: i * w + w * 0.15, w: w * 0.7, y: 150 - h, h, v, date: d.date, zero: v === 0 };
    });
});
const maxRef = computed(() => Math.max(1, ...(data.value?.referrers || []).map((r) => r.count)));
const label = (d) => new Date(`${d}T00:00:00`).toLocaleDateString(i18n.locale === 'fa' ? 'fa-IR' : 'en-US', { month: 'short', day: 'numeric' });
</script>

<template>
    <div class="space-y-7">
        <div class="flex gap-1 rounded-[8px] bg-soft p-1" role="group">
            <button v-for="d in [7, 30, 90]" :key="d" type="button" class="flex-1 rounded-[6px] px-3 py-1.5 text-sm font-medium"
                    :class="days === d ? 'bg-white shadow-card' : 'text-muted hover:text-ink'" :aria-pressed="days === d" @click="days = d">{{ t('stDays' + d) }}</button>
        </div>

        <p v-if="loading && !data" class="py-10 text-center text-muted">{{ t('loading') }}</p>
        <template v-if="data">
            <div class="grid grid-cols-2 gap-3">
                <button v-for="[key, name] in METRICS" :key="key" type="button" class="rounded-[8px] border p-4 text-start transition-colors"
                        :class="metric === key ? 'border-brand bg-brand/5' : 'border-line hover:bg-soft'" :aria-pressed="metric === key" @click="metric = key">
                    <div class="text-2xl font-bold tabular-nums">{{ fmt(data.totals[key]) }}</div>
                    <div class="mt-0.5 text-sm text-muted">{{ t(name) }}</div>
                </button>
            </div>

            <section>
                <h3 class="text-sm font-semibold">{{ t(METRICS.find((m) => m[0] === metric)[1]) }}</h3>
                <svg viewBox="0 0 600 170" class="mt-2 w-full" role="img" :aria-label="t(METRICS.find((m) => m[0] === metric)[1])" dir="ltr">
                    <line x1="0" y1="150.5" x2="600" y2="150.5" stroke="#E4E7EC" />
                    <rect v-for="b in bars" :key="b.date" :x="b.x" :y="b.y" :width="b.w" :height="b.h" rx="2" :fill="b.zero ? '#E4E7EC' : '#1D4FC4'">
                        <title>{{ label(b.date) }}: {{ fmt(b.v) }}</title>
                    </rect>
                    <text x="0" y="168" font-size="11" fill="#5B6470">{{ label(data.series[0].date) }}</text>
                    <text x="600" y="168" font-size="11" fill="#5B6470" text-anchor="end">{{ label(data.series[data.series.length - 1].date) }}</text>
                </svg>
            </section>

            <section>
                <h3 class="text-sm font-semibold">{{ t('stTop') }}</h3>
                <p v-if="!data.referrers.length" class="hint mt-2">{{ t('stNoRef') }}</p>
                <ul v-else class="mt-3 space-y-2.5">
                    <li v-for="r in data.referrers" :key="r.host">
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span dir="ltr" class="min-w-0 truncate">{{ r.host }}</span>
                            <span class="shrink-0 tabular-nums text-muted">{{ fmt(r.count) }}</span>
                        </div>
                        <div class="mt-1 h-1.5 rounded-full bg-soft"><div class="h-full rounded-full bg-brand" :style="{ width: (r.count / maxRef) * 100 + '%' }"></div></div>
                    </li>
                </ul>
            </section>

            <p class="hint">{{ t('stPrivacy') }}</p>
        </template>
    </div>
</template>
