<script setup>
import { lt } from './copy';
import Person from './Person.vue';
import SocialIcon from '../SocialIcon.vue';
</script>

<template>
    <div class="mx-auto flex w-full max-w-[34rem] items-center gap-5" aria-hidden="true">
        <div class="relative flex-1 overflow-hidden rounded-[6px] p-6 text-white" style="background: linear-gradient(135deg, #0b0f14, #1c2735 55%, #4a4a2a)">
            <Person :tone="6" :size="92" />
            <div class="mt-4 text-[1.75rem] font-bold leading-tight">{{ lt('mockName') }}</div>
            <div class="text-sm text-white/60">{{ lt('mockCity') }}</div>
            <span class="mt-5 block h-2.5 w-4/5 rounded bg-white/70"></span><span class="mt-2 block h-2.5 w-1/2 rounded bg-white/70"></span>
            <div class="mt-5 flex items-center gap-3"><SocialIcon name="github" :size="18" /><SocialIcon name="telegram" :size="18" /><span class="ms-auto text-sm">{{ lt('idView') }} →</span></div>
        </div>
        <div class="w-28 shrink-0 space-y-3">
            <div class="grid aspect-[4/5] place-items-center overflow-hidden rounded-[6px] bg-[#e9e4df]"><Person :tone="2" :size="90" /></div>
            <div class="grid aspect-[4/5] place-items-center overflow-hidden rounded-[6px] bg-[#e3c3c3] ring-[3px] ring-brand"><Person :tone="7" :size="90" /></div>
        </div>
    </div>
</template>
