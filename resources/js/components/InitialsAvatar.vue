<script setup>
import { computed } from 'vue';

// Mirrors App\Services\DefaultAvatars (same palette, same FNV-1a colour rule, same initials rule),
// so the dashboard preview matches what /{username}/avatar serves.
const PALETTE = ['#1D4FC4', '#0F766E', '#7C3AED', '#B4231A', '#9A5B00', '#0E7490', '#A21CAF', '#3F6212', '#BE185D', '#475569', '#4338CA', '#15803D'];
const props = defineProps({ name: { type: String, default: '' }, seed: { type: String, default: '' } });

function fnv1a(s) {
    let h = 0x811c9dc5;
    for (const b of new TextEncoder().encode(s)) { h ^= b; h = Math.imul(h, 0x01000193) >>> 0; }
    return h >>> 0;
}
const initials = computed(() => {
    const words = (props.name || '').trim().split(/[\s‌_.\-]+/u).filter(Boolean).slice(0, 2);
    const letters = words.map((w) => (w.match(/\p{L}|\p{N}/u) || [''])[0].toUpperCase()).filter(Boolean);
    return letters.length ? letters.join('') : '?';
});
const bg = computed(() => PALETTE[fnv1a((props.seed || '').trim().toLowerCase()) % PALETTE.length]);
</script>

<template>
    <svg viewBox="0 0 80 80" role="img" :aria-label="name" class="size-full">
        <rect width="80" height="80" :fill="bg" />
        <text x="40" y="40" dy=".35em" text-anchor="middle" fill="#fff" font-size="32" font-weight="600" font-family="Vazirmatn Variable, Vazirmatn, Tahoma, 'Segoe UI', Arial, sans-serif">{{ initials }}</text>
    </svg>
</template>
