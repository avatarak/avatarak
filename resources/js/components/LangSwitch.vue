<script setup>
import { i18n, setLocale } from '../i18n';
</script>

<template>
    <button class="btn btn-ghost !px-3 !py-1.5 text-sm" type="button"
            :aria-label="i18n.locale === 'fa' ? 'Switch to English' : 'تغییر به فارسی'"
            @click="setLocale(i18n.locale === 'fa' ? 'en' : 'fa')">
        {{ i18n.locale === 'fa' ? 'EN' : 'فا' }}
    </button>
</template>
