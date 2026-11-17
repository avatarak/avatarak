<script setup>
import { SOCIAL_PATHS } from '../icons';

defineProps({ name: String, size: { type: [Number, String], default: 22 } });
</script>

<template>
    <svg v-if="SOCIAL_PATHS[name]" :width="size" :height="size" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path :d="SOCIAL_PATHS[name]" />
    </svg>
    <span v-else class="inline-grid place-items-center rounded-full bg-current/100 text-[10px] font-bold uppercase"
          :style="{ width: size + 'px', height: size + 'px' }" aria-hidden="true">
        <span class="text-white">{{ name.charAt(0) }}</span>
    </span>
</template>
