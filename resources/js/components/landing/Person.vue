<script setup>
import { computed } from 'vue';

/**
 * A decorative, flat-illustration portrait (no real person, no photo). `tone` picks one of 12 looks:
 * skin tone, hair colour/style, glasses, beard, clothes and background all vary.
 */
const props = defineProps({ tone: { type: Number, default: 0 }, size: { type: [Number, String], default: 48 }, ring: Boolean });

const SKIN = ['#F2CBA7', '#E3B087', '#C98E66', '#8D5B3C', '#F7D9C0', '#A8704B'];
const HAIR = ['#2B2118', '#5A3A22', '#C28A3A', '#171717', '#8C8C8C', '#7A3E20'];
const CLOTH = ['#3563E9', '#101517', '#C2255C', '#0F9D7A', '#F59F00', '#7048E8'];
const BG = ['#F5B400', '#E6EEF9', '#2B3036', '#FBE3E0', '#DDEBDD', '#E9E4DF', '#C9D8F0', '#F3D9B1'];
const LOOKS = [
    { s: 0, h: 0, hs: 'short', c: 1, bg: 0, g: false, b: false },
    { s: 4, h: 1, hs: 'long', c: 2, bg: 5, g: true, b: false },
    { s: 3, h: 3, hs: 'curly', c: 0, bg: 2, g: false, b: false },
    { s: 1, h: 2, hs: 'bun', c: 3, bg: 3, g: false, b: false },
    { s: 2, h: 0, hs: 'short', c: 4, bg: 7, g: true, b: true },
    { s: 5, h: 3, hs: 'long', c: 5, bg: 4, g: false, b: false },
    { s: 0, h: 4, hs: 'bald', c: 0, bg: 6, g: true, b: true },
    { s: 4, h: 5, hs: 'curly', c: 2, bg: 1, g: false, b: false },
    { s: 1, h: 1, hs: 'short', c: 5, bg: 0, g: false, b: true },
    { s: 3, h: 0, hs: 'bun', c: 1, bg: 5, g: true, b: false },
    { s: 2, h: 3, hs: 'long', c: 3, bg: 6, g: false, b: false },
    { s: 5, h: 1, hs: 'short', c: 4, bg: 3, g: false, b: false },
];
const look = computed(() => { const l = LOOKS[Math.abs(props.tone) % LOOKS.length]; return { ...l, skin: SKIN[l.s], hair: HAIR[l.h], cloth: CLOTH[l.c], bg: BG[l.bg] }; });
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 100 100" aria-hidden="true" class="shrink-0 overflow-hidden rounded-full" :class="ring ? 'ring-2 ring-brand ring-offset-2' : ''">
        <rect width="100" height="100" :fill="look.bg" />
        <!-- hair behind the head -->
        <path v-if="look.hs === 'long'" d="M26 44 Q26 18 50 18 Q74 18 74 44 L78 78 L22 78 Z" :fill="look.hair" />
        <circle v-if="look.hs === 'bun'" cx="50" cy="16" r="9" :fill="look.hair" />
        <!-- shoulders + neck -->
        <path d="M10 100 Q12 74 50 72 Q88 74 90 100 Z" :fill="look.cloth" />
        <rect x="43" y="58" width="14" height="18" rx="5" :fill="look.skin" />
        <path d="M43 74 Q50 80 57 74 L57 72 L43 72 Z" :fill="look.skin" />
        <!-- head -->
        <ellipse cx="50" cy="44" rx="17" ry="20" :fill="look.skin" />
        <ellipse cx="33.2" cy="46" rx="3" ry="4.5" :fill="look.skin" /><ellipse cx="66.8" cy="46" rx="3" ry="4.5" :fill="look.skin" />
        <!-- hair on top -->
        <path v-if="look.hs === 'short' || look.hs === 'bun'" d="M32 42 Q30 22 50 22 Q70 22 68 42 Q64 32 50 31 Q36 32 32 42 Z" :fill="look.hair" />
        <path v-if="look.hs === 'long'" d="M31 40 Q33 22 50 22 Q67 22 69 40 Q60 30 50 31 Q40 30 31 40 Z" :fill="look.hair" />
        <g v-if="look.hs === 'curly'" :fill="look.hair"><circle cx="35" cy="30" r="8" /><circle cx="45" cy="24" r="8" /><circle cx="56" cy="24" r="8" /><circle cx="66" cy="30" r="8" /><circle cx="50" cy="29" r="9" /></g>
        <!-- beard -->
        <path v-if="look.b" d="M33 46 Q34 64 50 65 Q66 64 67 46 Q62 56 50 56 Q38 56 33 46 Z" :fill="look.hair" opacity=".92" />
        <!-- face -->
        <circle cx="43" cy="45" r="1.9" fill="#26201b" /><circle cx="57" cy="45" r="1.9" fill="#26201b" />
        <path d="M44.5 54 Q50 58.5 55.5 54" fill="none" stroke="#26201b" stroke-width="1.6" stroke-linecap="round" :opacity="look.b ? 0.55 : 1" />
        <g v-if="look.g" fill="none" stroke="#26201b" stroke-width="1.5"><circle cx="43" cy="45" r="6" /><circle cx="57" cy="45" r="6" /><path d="M49 45 H51" /></g>
    </svg>
</template>
