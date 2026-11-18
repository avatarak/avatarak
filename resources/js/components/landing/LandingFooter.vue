<script setup>
import Logo from '../Logo.vue';
import LangSwitch from '../LangSwitch.vue';
import Icon from '../Icon.vue';
import { lt } from './copy';

defineEmits(['login', 'register']);
const brand = window.__BRAND__ || {};
const year = new Date().getFullYear();
</script>

<template>
    <footer class="bg-[#101517] text-white">
        <div class="mx-auto grid max-w-[1180px] gap-10 px-5 py-20 sm:grid-cols-2 lg:grid-cols-[1.3fr_1fr_1fr_1fr_1fr]">
            <div>
                <Logo light />
                <div class="mt-6 flex items-center gap-2 text-sm text-white/70"><Icon name="globe" :size="18" /> {{ lt('langLabel') }} <LangSwitch class="!border-white/30 !bg-transparent !text-white hover:!bg-white/10" /></div>
            </div>
            <div>
                <h3 class="text-xl font-bold">{{ lt('footFeatures') }}</h3>
                <ul class="mt-5 space-y-4"><li><a href="#profile" class="hover:underline">{{ lt('fProfile') }}</a></li><li><a href="#verified" class="hover:underline">{{ lt('fVerified') }}</a></li><li><a href="#privacy" class="hover:underline">{{ lt('fPrivacy') }}</a></li></ul>
            </div>
            <div>
                <h3 class="text-xl font-bold">{{ lt('footDevelopers') }}</h3>
                <ul class="mt-5 space-y-4">
                    <li><a href="#developers" class="hover:underline">{{ lt('fApi') }}</a></li>
                    <li><a href="/docs" class="hover:underline">{{ lt('fDocs') }}</a></li>
                    <li><a href="https://github.com/avatarak/avatarak" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm hover:bg-white/20">GitHub <Icon name="external" :size="14" /></a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-xl font-bold">{{ lt('footSupport') }}</h3>
                <ul class="mt-5 space-y-4"><li><a href="#faq" class="hover:underline">{{ lt('fFaq') }}</a></li></ul>
            </div>
            <div>
                <h3 class="text-xl font-bold">{{ lt('footAccount') }}</h3>
                <ul class="mt-5 space-y-4">
                    <li><button type="button" class="hover:underline" @click="$emit('login')">{{ lt('fLogin') }}</button></li>
                    <li><button type="button" class="hover:underline" @click="$emit('register')">{{ lt('fRegister') }}</button></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/10 px-5 py-6 text-center text-sm text-white/60">© {{ year }} {{ brand.name_en || brand.name }}. {{ lt('rights') }}</div>
    </footer>
</template>
