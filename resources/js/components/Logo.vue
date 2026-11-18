<script setup>
import { i18n } from '../i18n';
import LogoMark from './LogoMark.vue';
defineProps({ light: Boolean });
const brand = window.__BRAND__ || {};
</script>

<template>
    <span class="inline-flex items-center gap-2 font-display text-lg font-bold tracking-tight" :class="light ? 'text-white' : ''">
        <img v-if="brand.logo_url" :src="brand.logo_url" :alt="brand.name" class="size-8 rounded-lg object-contain">
        <LogoMark v-else :size="30" :class="light ? 'text-[#8FB0FF]' : 'text-brand'" :face="light ? '#101517' : '#fff'" />
        <span>{{ i18n.locale === 'en' ? (brand.name_en || brand.name) : brand.name }}</span>
    </span>
</template>
