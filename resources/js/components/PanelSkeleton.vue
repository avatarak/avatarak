<script setup>
import { t } from '../i18n';
</script>

<template>
    <div class="flex h-dvh flex-col bg-white" role="status" :aria-label="t('loading')">
        <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
            <div class="sk h-8 w-32"></div>
            <div class="flex items-center gap-2"><div class="sk h-9 w-12"></div><div class="sk size-9 !rounded-full"></div></div>
        </div>
        <div class="mx-auto w-full max-w-xl flex-1 space-y-4 overflow-hidden px-4 py-5">
            <div class="sk h-11 w-full !rounded-full lg:hidden"></div>
            <div class="sk h-6 w-40"></div>
            <div class="flex items-center gap-4 rounded-[12px] border border-line p-4">
                <div class="sk size-16 shrink-0 !rounded-full"></div>
                <div class="flex-1 space-y-2.5"><div class="sk h-4 w-2/3"></div><div class="sk h-3 w-1/2"></div></div>
            </div>
            <div v-for="i in 5" :key="i" class="flex items-center gap-3 rounded-[12px] border border-line p-4">
                <div class="sk size-9 shrink-0"></div>
                <div class="flex-1 space-y-2"><div class="sk h-3.5 w-1/3"></div><div class="sk h-3 w-3/5"></div></div>
            </div>
        </div>
    </div>
</template>
