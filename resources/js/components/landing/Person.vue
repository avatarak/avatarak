<script setup>
// A decorative, anonymous avatar (no real people / photos): tinted circle with a person silhouette.
defineProps({ tone: { type: Number, default: 0 }, size: { type: [Number, String], default: 48 }, ring: Boolean });
const TONES = [['#F5B400', '#2B2118'], ['#E9E4DF', '#4A3A2F'], ['#2B3036', '#C9B8A6'], ['#C97B5B', '#2E1B14'], ['#D9A441', '#3A2A1C'], ['#9BBBD8', '#26323F'], ['#B7C9A8', '#2F3A2A'], ['#E3C3C3', '#4A2E2E']];
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 100 100" aria-hidden="true" class="shrink-0 overflow-hidden rounded-full" :class="ring ? 'ring-2 ring-brand ring-offset-2' : ''">
        <circle cx="50" cy="50" r="50" :fill="TONES[tone % TONES.length][0]" />
        <circle cx="50" cy="40" r="17" :fill="TONES[tone % TONES.length][1]" opacity=".9" />
        <path d="M14 100c2-22 17-34 36-34s34 12 36 34z" :fill="TONES[tone % TONES.length][1]" opacity=".9" />
    </svg>
</template>
