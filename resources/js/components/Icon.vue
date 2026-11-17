<script setup>
import UI from '../../data/ui-icons.json';

// Static markup generated from @tabler/icons by scripts/build-icons.mjs (trusted, never user input).
defineProps({ name: String, size: { type: [Number, String], default: 22 }, flip: Boolean });
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" :class="{ 'rtl:rotate-180': flip }" v-html="UI[name]" />
</template>
