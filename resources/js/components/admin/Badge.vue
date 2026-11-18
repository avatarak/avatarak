<script setup>
defineProps({ tone: { type: String, default: 'gray' } });
const TONES = {
    gray: 'bg-soft text-ink-2', green: 'bg-emerald-50 text-emerald-800', red: 'bg-red-50 text-red-800', amber: 'bg-amber-50 text-amber-900',
    blue: 'bg-blue-50 text-blue-800', violet: 'bg-violet-50 text-violet-800',
};
</script>

<template>
    <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold" :class="TONES[tone] || TONES.gray"><slot /></span>
</template>
