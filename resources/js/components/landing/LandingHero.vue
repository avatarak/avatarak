<script setup>
import { ref } from 'vue';
import Icon from '../Icon.vue';
import Person from './Person.vue';
import SocialIcon from '../SocialIcon.vue';
import Verified from './Verified.vue';
import { i18n } from '../../i18n';
import { lt } from './copy';

const emit = defineEmits(['start']);
const email = ref('');

// decorative sample profiles (not real people)
const cards = [
    { fa: 'سارا احمدی', en: 'Sara Ahmadi', rf: 'طراح محصول', re: 'Product designer', tone: 0, icons: ['instagram', 'x', 'github'] },
    { fa: 'امیر رضایی', en: 'Amir Rezaei', rf: 'توسعه‌دهنده‌ی وب', re: 'Web developer', tone: 5, icons: ['github', 'linkedin', 'telegram'] },
    { fa: 'مینا کریمی', en: 'Mina Karimi', rf: 'نویسنده و مترجم', re: 'Writer & translator', tone: 3, icons: ['telegram', 'instagram', 'youtube'] },
];
const fa = () => i18n.locale === 'fa';
</script>

<template>
    <section class="profile-canvas relative overflow-hidden">
        <div class="mx-auto grid max-w-[1060px] items-center gap-12 px-5 py-16 lg:min-h-[680px] lg:grid-cols-[minmax(0,34rem)_1fr] lg:py-24">
            <div class="text-white">
                <h1 class="text-balance text-[3rem] font-bold leading-[1.1] tracking-tight sm:text-[5rem] sm:leading-[1.05]" style="text-shadow: 0 1px 24px rgb(0 0 0 / .08)">{{ lt('heroTitle') }}</h1>
                <p class="mt-6 max-w-[30rem] text-lg leading-[1.55] text-white/95">{{ lt('heroSub') }}</p>
                <form class="mt-8 flex max-w-[34rem] flex-col gap-3 sm:flex-row" @submit.prevent="emit('start', email)">
                    <input v-model="email" type="email" dir="ltr" class="field !min-h-[56px] text-start text-ink sm:flex-1" :placeholder="lt('emailPh')" :aria-label="lt('emailPh')" autocomplete="email">
                    <button class="btn btn-primary !min-h-[56px] !px-6">{{ lt('claim') }} <Icon name="chevron" :size="16" flip /></button>
                </form>
            </div>

            <div class="relative hidden h-[30rem] lg:block" aria-hidden="true">
                <article v-for="(c, i) in cards" :key="c.en" class="card absolute top-1/2 w-[22rem] -translate-y-1/2 !rounded-[4px] p-8" :style="{ insetInlineStart: i * 24 + 'rem' }">
                    <Person :tone="c.tone" :size="96" />
                    <div class="mt-5 text-[2rem] font-bold leading-tight">{{ fa() ? c.fa : c.en }}</div>
                    <div class="text-base text-ink/70">{{ fa() ? c.rf : c.re }}</div>
                    <div class="mt-5 flex items-center gap-3 text-ink"><Verified /><span class="h-px w-3 bg-ink/35"></span><SocialIcon v-for="n in c.icons" :key="n" :name="n" :size="18" /></div>
                    <div class="mt-5 grid grid-cols-2 gap-3"><span class="h-[42px] rounded-[4px] border border-line"></span><span class="h-[42px] rounded-[4px] border border-line"></span></div>
                </article>
            </div>
        </div>
    </section>
</template>
