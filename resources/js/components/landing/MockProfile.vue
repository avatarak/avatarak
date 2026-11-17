<script setup>
import { lt } from './copy';
import Person from './Person.vue';
import Verified from './Verified.vue';
import SocialIcon from '../SocialIcon.vue';
import Icon from '../Icon.vue';
</script>

<template>
    <div class="mx-auto w-full max-w-[38rem] space-y-4" aria-hidden="true">
        <div class="rounded-[4px] bg-white p-2 shadow-card"><div class="rounded-full border border-ink/70 px-5 py-2.5 text-base text-ink/80" dir="ltr">avatarak.ir/sara</div></div>
        <div class="card relative overflow-hidden !rounded-[4px] px-6 pb-6 pt-6 sm:px-10">
            <div class="absolute inset-x-0 top-0 h-28 bg-[color-mix(in_srgb,var(--brand)_18%,#fff)]"></div>
            <div class="relative w-fit"><Person :tone="0" :size="112" /><span class="absolute end-0 top-0 grid size-8 place-items-center rounded-full bg-ink text-white"><Icon name="qr" :size="16" /></span></div>
            <div class="mt-3 text-[1.75rem] font-bold leading-tight">{{ lt('mockName') }}</div>
            <div class="text-base text-ink/70">{{ lt('mockCity') }}</div>
            <div class="mt-4 flex items-center gap-3"><Verified /><span class="h-px w-3 bg-ink/35"></span><SocialIcon name="instagram" :size="18" /><SocialIcon name="telegram" :size="18" /><SocialIcon name="github" :size="18" /></div>
            <p class="mt-4 text-base leading-[1.45]">{{ lt('mockBio') }}</p>
            <div class="mt-5 grid grid-cols-2 gap-3"><span class="accent-btn" style="--accent: var(--brand)">{{ lt('mockShare') }}</span><span class="accent-btn" style="--accent: var(--brand)">{{ lt('mockSite') }}</span></div>
        </div>
        <div class="card !rounded-[4px] px-6 py-6 sm:px-10">
            <div class="text-lg font-bold">{{ lt('mockLinks') }}</div>
            <div class="mt-2 divide-y divide-line">
                <div v-for="(c, i) in [['#7a2a52', 'mockLink1', 'mockLinkD1'], ['#2f6f8f', 'mockLink2', 'mockLinkD2']]" :key="i" class="flex items-start gap-4 py-4">
                    <span class="aspect-video w-32 shrink-0 rounded-[4px]" :style="{ background: `linear-gradient(135deg, ${c[0]}, color-mix(in srgb, ${c[0]} 55%, #fff))` }"></span>
                    <span class="min-w-0"><span class="block text-sm font-bold text-brand">{{ lt(c[1]) }} ↗</span><span class="mt-1 block text-sm leading-6 text-ink/75">{{ lt(c[2]) }}</span></span>
                </div>
            </div>
        </div>
    </div>
</template>
