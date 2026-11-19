<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import Icon from '../Icon.vue';
import Person from './Person.vue';
import SocialIcon from '../SocialIcon.vue';
import Verified from './Verified.vue';
import { dir } from '../../i18n';
import { lt } from './copy';

const emit = defineEmits(['start']);
const email = ref('');

// decorative sample profiles with generated, fictional portraits; copy lives in copy.js
const looks = [{ tone: 1, icons: ['instagram', 'x', 'github'], tint: '#3563E9' }, { tone: 4, icons: ['github', 'linkedin', 'telegram'], tint: '#0F9D7A' }, { tone: 3, icons: ['telegram', 'instagram', 'youtube'], tint: '#C2255C' }];

/*
 * Card stack: the next card slides forward over the current one while the current one drops to the back.
 * position 0 = front, 1 = peeking behind, 2 = furthest back. Auto-advances; pauses on hover / hidden tab;
 * with prefers-reduced-motion it stays a static stack (clicking still advances).
 */
const COUNT = 3;
const active = ref(0);
const paused = ref(false);
const entered = ref(false);
let timer = null;

const pos = (i) => (i - active.value + COUNT) % COUNT;
const next = () => { active.value = (active.value + 1) % COUNT; };
const jump = (i) => { active.value = i; };

function cardStyle(i) {
    const p = pos(i);
    const sign = dir.value === 'rtl' ? -1 : 1;           // stack peeks toward the page edge, mirrored in RTL
    const x = [0, 58, 108][p], rot = [0, 3, 6][p], scale = [1, 0.94, 0.88][p], lift = entered.value ? 0 : 28;
    return {
        zIndex: 30 - p * 10,
        opacity: entered.value ? [1, 0.94, 0.8][p] : 0,
        transform: `translate(${sign * x}px, calc(-50% + ${lift}px)) rotate(${sign * rot}deg) scale(${scale})`,
        transitionDelay: entered.value ? '0ms' : `${p * 110}ms`,
    };
}

onMounted(() => {
    requestAnimationFrame(() => requestAnimationFrame(() => { entered.value = true; }));
    const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    if (!reduce) timer = setInterval(() => { if (!paused.value && !document.hidden) next(); }, 3800);
});
onBeforeUnmount(() => clearInterval(timer));
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

            <div class="relative hidden h-[34rem] cursor-pointer lg:block" aria-hidden="true" @mouseenter="paused = true" @mouseleave="paused = false" @click="next">
                <article v-for="(c, i) in lt('heroCards')" :key="c.name"
                         class="card absolute start-0 top-1/2 w-[21.5rem] overflow-hidden !rounded-[4px] transition-[transform,opacity,box-shadow] duration-[850ms] ease-[cubic-bezier(.22,.9,.3,1)] will-change-transform"
                         :class="pos(i) === 0 ? 'shadow-[0_18px_50px_rgb(16_21_23/.28)]' : ''" :style="cardStyle(i)">
                    <div class="h-24" :style="{ background: `color-mix(in srgb, ${looks[i].tint} 20%, #fff)` }"></div>
                    <div class="px-8 pb-8">
                        <div class="-mt-14 w-fit rounded-full ring-4 ring-white"><Person :tone="looks[i].tone" :size="108" eager /></div>
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
                <!-- progress dots -->
                <div class="absolute inset-x-0 bottom-0 flex justify-center gap-2" :style="{ paddingInlineEnd: '108px' }">
                    <button v-for="i in COUNT" :key="i" type="button" tabindex="-1" class="h-2 rounded-full bg-white transition-all duration-500"
                            :class="active === i - 1 ? 'w-6 opacity-100' : 'w-2 opacity-50'" @click.stop="jump(i - 1)"></button>
                </div>
            </div>
        </div>
    </section>
</template>
