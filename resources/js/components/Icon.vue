<script setup>
import { STROKE } from '../icons';

defineProps({ name: String, size: { type: [Number, String], default: 22 }, flip: Boolean });
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" :class="{ 'rtl:rotate-180': flip }">
        <path :d="STROKE[name]" :stroke-width="name === 'grip' ? 3 : undefined" />
    </svg>
</template>
