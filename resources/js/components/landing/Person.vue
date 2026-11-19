<script setup>
import { computed } from 'vue';

/**
 * A portrait for the decorative sample profiles on the home page. The photos in public/images/landing are
 * AI-generated, fictional people (nobody real); `tone` just picks one, so the same person keeps the same face in every preview.
 */
const props = defineProps({ tone: { type: Number, default: 0 }, size: { type: [Number, String], default: 48 }, ring: Boolean, eager: Boolean });

// tone 0, 1 = Sara (the "Sara Ahmadi" previews), 3 = Mina, 4 = Amir; the other numbers are different faces so a grid never repeats one
const PORTRAITS = ['sara', 'sara', 'portrait-2', 'mina', 'amir', 'portrait-5', 'portrait-5', 'portrait-7', 'portrait-8', 'portrait-9'];
const portrait = computed(() => `/images/landing/${PORTRAITS[Math.abs(props.tone) % PORTRAITS.length]}.webp`);
</script>

<template>
    <img :src="portrait" :width="size" :height="size" alt="" aria-hidden="true" decoding="async" :loading="eager ? 'eager' : 'lazy'"
         class="shrink-0 rounded-full object-cover" :class="ring ? 'ring-2 ring-brand ring-offset-2' : ''">
</template>
