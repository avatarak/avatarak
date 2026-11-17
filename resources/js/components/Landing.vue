<script setup>
import { ref } from 'vue';
import Logo from './Logo.vue';
import LangSwitch from './LangSwitch.vue';
import AuthCard from './AuthCard.vue';
import Icon from './Icon.vue';
import SocialIcon from './SocialIcon.vue';
import { t, i18n } from '../i18n';

const brand = window.__BRAND__ || {};
const auth = ref('register');
const authOpen = ref(false);
const show = (mode) => { auth.value = mode; authOpen.value = true; };
const email = ref('');

// decorative sample profiles (no real people / data)
const cards = [
    { fa: 'سارا احمدی', en: 'Sara Ahmadi', role_fa: 'طراح محصول', role_en: 'Product designer', tone: 0.55, icons: ['instagram', 'x', 'github'] },
    { fa: 'امیر رضایی', en: 'Amir Rezaei', role_fa: 'توسعه‌دهنده‌ی وب', role_en: 'Web developer', tone: 0.35, icons: ['github', 'linkedin', 'telegram'] },
    { fa: 'مینا کریمی', en: 'Mina Karimi', role_fa: 'نویسنده و مترجم', role_en: 'Writer & translator', tone: 0.75, icons: ['telegram', 'instagram', 'youtube'] },
];
const fa = () => i18n.locale === 'fa';
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <header class="relative z-10 flex h-[86px] items-center justify-between bg-white px-5 sm:px-8">
            <Logo />
            <nav class="flex items-center gap-2">
                <LangSwitch />
                <button class="btn btn-ghost" @click="show('login')">{{ t('login') }}</button>
                <button class="btn btn-primary hidden sm:inline-flex" @click="show('register')">{{ t('start') }}</button>
            </nav>
        </header>

        <main class="profile-canvas relative flex-1 overflow-hidden">
            <div class="mx-auto grid max-w-7xl items-center gap-10 px-5 py-14 sm:px-8 lg:min-h-[640px] lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:py-20">
                <section class="text-white">
                    <h1 class="text-5xl font-bold leading-[1.15] tracking-tight text-balance sm:text-7xl" style="text-shadow: 0 1px 24px rgb(0 0 0 / .08)">
                        {{ i18n.locale === 'en' ? (brand.tagline_en || brand.tagline) : brand.tagline }}
                    </h1>
                    <p class="mt-6 max-w-xl text-xl leading-9 text-white/90">{{ t('heroSub') }}</p>
                    <form class="mt-9 flex max-w-xl flex-col gap-3 sm:flex-row" @submit.prevent="show('register')">
                        <input v-model="email" type="email" dir="ltr" class="field text-start text-ink sm:flex-1" :placeholder="t('emailPlaceholder')" :aria-label="t('email')" autocomplete="email">
                        <button class="btn !min-h-[48px] border-white bg-brand text-white hover:!bg-ink">{{ t('claim') }} <Icon name="chevron" :size="18" flip /></button>
                    </form>
                </section>

                <section class="relative hidden h-[27rem] lg:block" aria-hidden="true">
                    <article v-for="(c, i) in cards" :key="c.en" class="card absolute top-1/2 w-[20rem] -translate-y-1/2 p-6"
                             :style="{ insetInlineStart: i * 22 + 'rem' }">
                        <div class="size-20 rounded-full" :style="{ background: `color-mix(in srgb, var(--brand) ${c.tone * 100}%, #E6E8EC)` }"></div>
                        <div class="mt-5 text-3xl font-bold leading-tight">{{ fa() ? c.fa : c.en }}</div>
                        <div class="mt-1 text-muted">{{ fa() ? c.role_fa : c.role_en }}</div>
                        <div class="mt-5 flex gap-3 text-ink"><SocialIcon v-for="n in c.icons" :key="n" :name="n" :size="22" /></div>
                        <div class="mt-5 grid gap-2"><span class="h-11 rounded-[4px] border border-line"></span><span class="h-11 rounded-[4px] border border-line"></span></div>
                    </article>
                </section>
            </div>
        </main>

        <section id="how" class="mx-auto grid w-full max-w-7xl gap-10 px-5 py-16 sm:grid-cols-3 sm:px-8">
            <div v-for="n in [1, 2, 3]" :key="n">
                <div class="text-sm font-bold text-brand">0{{ n }}</div>
                <h3 class="mt-2 text-xl font-bold">{{ t('how' + n) }}</h3>
                <p class="mt-2 leading-7 text-muted">{{ t('how' + n + 'd') }}</p>
            </div>
        </section>

        <AuthCard :open="authOpen" :mode="auth" :email="email" @close="authOpen = false" @switch="auth = $event" />
    </div>
</template>
