<script setup>
import { ref } from 'vue';
import Icon from '../Icon.vue';
import Person from './Person.vue';
import SocialIcon from '../SocialIcon.vue';
import Verified from './Verified.vue';
import { lt } from './copy';

const emit = defineEmits(['start']);
const email = ref('');

// decorative sample profiles (illustrated, not real people); copy lives in copy.js
const looks = [{ tone: 1, icons: ['instagram', 'x', 'github'], tint: '#3563E9' }, { tone: 4, icons: ['github', 'linkedin', 'telegram'], tint: '#0F9D7A' }, { tone: 3, icons: ['telegram', 'instagram', 'youtube'], tint: '#C2255C' }];
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

            <div class="relative hidden h-[36rem] lg:block" aria-hidden="true">
                <article v-for="(c, i) in lt('heroCards')" :key="c.name" class="card absolute top-1/2 w-[23rem] -translate-y-1/2 overflow-hidden !rounded-[4px]" :style="{ insetInlineStart: i * 25 + 'rem' }">
                    <div class="h-24" :style="{ background: `color-mix(in srgb, ${looks[i].tint} 20%, #fff)` }"></div>
                    <div class="px-8 pb-8">
                        <div class="-mt-14 w-fit rounded-full ring-4 ring-white"><Person :tone="looks[i].tone" :size="108" /></div>
                        <div class="mt-4 text-[2rem] font-bold leading-tight">{{ c.name }}</div>
                        <div class="text-base text-ink/75">{{ c.role }}</div>
                        <div class="text-base text-ink/75">{{ c.city }}</div>
                        <div class="mt-5 flex items-center gap-3 text-ink"><Verified /><span class="h-px w-3 bg-ink/35"></span><SocialIcon v-for="n in looks[i].icons" :key="n" :name="n" :size="18" /></div>
                        <p class="mt-5 line-clamp-3 text-base leading-[1.45]">{{ c.bio }}</p>
                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <span class="accent-btn" :style="{ '--accent': looks[i].tint }">{{ lt('mockShare') }}</span>
                            <span class="accent-btn" :style="{ '--accent': looks[i].tint }">{{ lt('mockSite') }}</span>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
