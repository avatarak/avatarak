<script setup>
import { i18n } from '../i18n';
const brand = window.__BRAND__ || {};
</script>

<template>
    <span class="inline-flex items-center gap-2 font-display text-lg font-bold tracking-tight">
        <img v-if="brand.logo_url" :src="brand.logo_url" :alt="brand.name" class="size-8 rounded-lg object-contain">
        <span v-else class="grid size-8 place-items-center rounded-lg bg-brand text-sm text-white" aria-hidden="true">
            {{ (brand.name_en || brand.name || '?').charAt(0) }}
        </span>
        <span>{{ i18n.locale === 'en' ? (brand.name_en || brand.name) : brand.name }}</span>
    </span>
</template>
