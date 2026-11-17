<script setup>
import { ref } from 'vue';
import Logo from './Logo.vue';
import LangSwitch from './LangSwitch.vue';
import AuthCard from './AuthCard.vue';
import { t, i18n } from '../i18n';

const brand = window.__BRAND__ || {};
const auth = ref(null); // 'login' | 'register' | null

// decorative sample profiles (no real data)
const cards = [
    { fa: 'سارا احمدی', en: 'Sara Ahmadi', bio: 'طراح محصول', bio_en: 'Product designer', tone: 18, pos: 'top-0 start-6 rotate-3 rtl:-rotate-3' },
    { fa: 'امیر رضایی', en: 'Amir Rezaei', bio: 'توسعه‌دهنده وب', bio_en: 'Web developer', tone: 8, pos: 'top-36 end-0 -rotate-2 rtl:rotate-2' },
    { fa: 'مینا کریمی', en: 'Mina Karimi', bio: 'نویسنده و مترجم', bio_en: 'Writer & translator', tone: 28, pos: 'top-72 start-14 rotate-1 rtl:-rotate-1' },
];
</script>

<template>
    <div class="mx-auto flex min-h-screen max-w-6xl flex-col px-5">
        <header class="flex items-center justify-between py-6">
            <Logo />
            <nav class="flex items-center gap-2">
                <LangSwitch />
                <button class="btn btn-ghost" @click="auth = 'login'">{{ t('login') }}</button>
                <button class="btn btn-primary hidden sm:inline-flex" @click="auth = 'register'">{{ t('register') }}</button>
            </nav>
        </header>

        <main class="grid flex-1 items-center gap-12 py-10 lg:grid-cols-2 lg:py-16">
            <section>
                <h1 class="font-display text-4xl font-bold leading-[1.25] tracking-tight text-balance sm:text-6xl">
                    {{ i18n.locale === 'en' ? (brand.tagline_en || brand.tagline) : brand.tagline }}
                </h1>
                <p class="mt-6 max-w-md text-lg leading-8 text-muted">{{ t('heroSub') }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <button class="btn btn-primary !px-6 !py-3" @click="auth = 'register'">{{ t('start') }}</button>
                    <a class="btn btn-ghost !px-6 !py-3" href="#how">{{ t('learn') }}</a>
                </div>
            </section>

            <section class="relative hidden h-[27rem] lg:block" aria-hidden="true">
                <div v-for="c in cards" :key="c.en" class="card absolute w-64 p-4" :class="c.pos">
                    <div class="flex items-center gap-3">
                        <div class="size-12 rounded-full"
                             :style="{ background: `color-mix(in srgb, var(--brand) ${c.tone * 3}%, var(--line))` }"></div>
                        <div>
                            <div class="font-display font-bold">{{ i18n.locale === 'fa' ? c.fa : c.en }}</div>
                            <div class="text-sm text-muted">{{ i18n.locale === 'fa' ? c.bio : c.bio_en }}</div>
                        </div>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <span class="h-2 w-12 rounded-full bg-line"></span>
                        <span class="h-2 w-8 rounded-full bg-line"></span>
                    </div>
                </div>
            </section>
        </main>

        <section id="how" class="grid gap-8 border-t border-line py-14 sm:grid-cols-3">
            <div v-for="n in [1, 2, 3]" :key="n">
                <div class="font-display text-sm font-bold text-brand">0{{ n }}</div>
                <h3 class="mt-2 font-display text-lg font-bold">{{ t('how' + n) }}</h3>
                <p class="mt-2 leading-7 text-muted">{{ t('how' + n + 'd') }}</p>
            </div>
        </section>

        <AuthCard v-if="auth" :mode="auth" @close="auth = null" @switch="auth = $event" />
    </div>
</template>
