<script setup>
import Logo from '../Logo.vue';
import LangSwitch from '../LangSwitch.vue';
import { t } from '../../i18n';
import { lt } from './copy';

defineEmits(['login', 'register']);
</script>

<template>
    <header class="relative z-10 flex h-[86px] items-center justify-between bg-white px-5 sm:px-8">
        <div class="flex items-center gap-10">
            <a href="/" aria-label="home"><Logo /></a>
            <nav class="hidden items-center gap-8 text-base font-semibold md:flex" aria-label="main">
                <a href="#profile" class="hover:text-brand">{{ lt('navFeatures') }}</a>
                <a href="#developers" class="hover:text-brand">{{ lt('navDevelopers') }}</a>
                <a href="/docs" class="hover:text-brand">{{ lt('navDocs') }}</a>
                <a href="#pricing" class="hover:text-brand">{{ t('prTitle') }}</a>
                <a href="#faq" class="hover:text-brand">{{ lt('navFaq') }}</a>
            </nav>
        </div>
        <div class="flex items-center gap-2">
            <LangSwitch />
            <button class="btn btn-ghost" @click="$emit('login')">{{ t('login') }}</button>
            <button class="btn btn-primary hidden sm:inline-flex" @click="$emit('register')">{{ t('start') }}</button>
        </div>
    </header>
</template>
