<script setup>
import { lt } from './copy';
import Person from './Person.vue';
import SocialIcon from '../SocialIcon.vue';
</script>

<template>
    <div class="relative mx-auto h-[19rem] w-full max-w-[32rem]" aria-hidden="true">
        <div class="absolute start-0 top-0 w-[78%] rounded-[6px] bg-[#8c8f94] p-6 text-white">
            <Person :tone="1" :size="88" />
            <div class="mt-4 text-[1.75rem] font-bold leading-tight">{{ lt('mockName') }}</div>
            <div class="text-sm text-white/80">{{ lt('mockCity') }}</div>
            <span class="mt-4 block h-2.5 w-4/5 rounded bg-white/80"></span><span class="mt-2 block h-2.5 w-1/2 rounded bg-white/80"></span>
            <div class="mt-5 flex gap-3"><SocialIcon name="github" :size="18" /><SocialIcon name="x" :size="18" /><SocialIcon name="telegram" :size="18" /></div>
        </div>
        <div class="absolute bottom-0 end-0 w-[52%] min-w-[11rem] rounded-[4px] bg-white p-4 shadow-card">
            <div class="text-base font-bold">{{ lt('privSwitch') }}</div>
            <div class="mt-3 flex items-center justify-between"><span class="h-6 w-20 rounded-full bg-line sm:w-32"></span><span class="relative h-6 w-11 rounded-full border border-ink"><span class="absolute start-0.5 top-0.5 size-4 rounded-full bg-ink"></span></span></div>
        </div>
    </div>
</template>
