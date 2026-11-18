<script setup>
import { computed, ref } from 'vue';
import { fmtDay, fmtNum } from '../../admin/lib';

/** Tiny dependency-free chart: lines or grouped bars, with a hover read-out and a legend. */
const props = defineProps({
    rows: { type: Array, required: true },                 // [{date, a, b…}]
    series: { type: Array, required: true },               // [{key, label, color}]
    mode: { type: String, default: 'line' },               // line | bars
    height: { type: Number, default: 200 },
});

const W = 640; const PAD = { l: 8, r: 8, t: 10, b: 22 };
const hover = ref(null);

const max = computed(() => {
    const m = Math.max(1, ...props.rows.flatMap((r) => props.series.map((s) => r[s.key] || 0)));
    const mag = 10 ** Math.floor(Math.log10(m));
    return Math.ceil(m / mag) * mag;                       // a round ceiling for tidy gridlines
});
const innerW = W - PAD.l - PAD.r; const innerH = computed(() => props.height - PAD.t - PAD.b);
const x = (i) => PAD.l + (props.rows.length <= 1 ? innerW / 2 : (i / (props.rows.length - 1)) * innerW);
const y = (v) => PAD.t + innerH.value - (v / max.value) * innerH.value;
const lines = computed(() => props.series.map((s) => ({ ...s, d: props.rows.map((r, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(r[s.key] || 0).toFixed(1)}`).join(' ') })));
const area = computed(() => {
    const s = props.series[0]; if (!s || !props.rows.length) return '';
    return `${lines.value[0].d} L${x(props.rows.length - 1).toFixed(1)},${y(0)} L${x(0).toFixed(1)},${y(0)} Z`;
});
const slot = computed(() => innerW / Math.max(1, props.rows.length));
const bars = computed(() => props.rows.flatMap((r, i) => props.series.map((s, k) => {
    const bw = Math.max(2, (slot.value * 0.8) / props.series.length);
    const v = r[s.key] || 0; const h = Math.max(v ? 2 : 0, (v / max.value) * innerH.value);
    return { key: `${i}-${s.key}`, x: PAD.l + i * slot.value + slot.value * 0.1 + k * bw, w: bw - 0.5, y: PAD.t + innerH.value - h, h, color: s.color };
})));
const ticks = computed(() => [0, 0.25, 0.5, 0.75, 1].map((f) => ({ y: y(max.value * f), v: max.value * f })));

function move(e) {
    const box = e.currentTarget.getBoundingClientRect();
    const px = ((e.clientX - box.left) / box.width) * W;
    const i = props.mode === 'bars' ? Math.floor((px - PAD.l) / slot.value) : Math.round(((px - PAD.l) / innerW) * (props.rows.length - 1));
    hover.value = i >= 0 && i < props.rows.length ? i : null;
}
const tip = computed(() => (hover.value == null ? null : { row: props.rows[hover.value], left: ((props.mode === 'bars' ? PAD.l + hover.value * slot.value + slot.value / 2 : x(hover.value)) / W) * 100 }));
</script>

<template>
    <div>
        <div class="mb-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
            <span v-for="s in series" :key="s.key" class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm" :style="{ background: s.color }"></span>{{ s.label }}</span>
        </div>
        <div class="relative" dir="ltr">
            <svg :viewBox="`0 0 ${W} ${height}`" class="block w-full" role="img" @mousemove="move" @mouseleave="hover = null">
                <g>
                    <line v-for="tk in ticks" :key="tk.y" :x1="PAD.l" :x2="W - PAD.r" :y1="tk.y" :y2="tk.y" stroke="#E4E7EC" stroke-width="1" />
                    <text :x="PAD.l" :y="PAD.t - 1" font-size="10" fill="#5B6470">{{ fmtNum(max) }}</text>
                </g>
                <template v-if="mode === 'line'">
                    <path :d="area" :fill="series[0]?.color" opacity=".08" />
                    <path v-for="l in lines" :key="l.key" :d="l.d" fill="none" :stroke="l.color" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
                    <template v-if="hover != null">
                        <line :x1="x(hover)" :x2="x(hover)" :y1="PAD.t" :y2="PAD.t + innerH" stroke="#9AA3AF" stroke-dasharray="3 3" />
                        <circle v-for="s in series" :key="s.key" :cx="x(hover)" :cy="y(rows[hover][s.key] || 0)" r="3.5" :fill="s.color" stroke="#fff" stroke-width="1.5" />
                    </template>
                </template>
                <template v-else>
                    <rect v-for="b in bars" :key="b.key" :x="b.x" :y="b.y" :width="b.w" :height="b.h" rx="1.5" :fill="b.color" />
                    <rect v-if="hover != null" :x="PAD.l + hover * slot" :y="PAD.t" :width="slot" :height="innerH" fill="#101517" opacity=".05" />
                </template>
                <text :x="PAD.l" :y="height - 6" font-size="10" fill="#5B6470">{{ rows.length ? fmtDay(rows[0].date) : '' }}</text>
                <text :x="W - PAD.r" :y="height - 6" font-size="10" fill="#5B6470" text-anchor="end">{{ rows.length ? fmtDay(rows[rows.length - 1].date) : '' }}</text>
            </svg>
            <div v-if="tip" class="pointer-events-none absolute top-0 z-10 -translate-x-1/2 rounded-[6px] bg-ink px-2.5 py-1.5 text-xs text-white shadow-card" :style="{ left: Math.min(88, Math.max(12, tip.left)) + '%' }">
                <div class="mb-0.5 font-semibold">{{ fmtDay(tip.row.date) }}</div>
                <div v-for="s in series" :key="s.key" class="flex items-center gap-1.5 whitespace-nowrap"><span class="size-2 rounded-full" :style="{ background: s.color }"></span>{{ s.label }}: <b class="tabular-nums">{{ fmtNum(tip.row[s.key] || 0) }}</b></div>
            </div>
        </div>
    </div>
</template>
