<script setup>
import Icon from '../Icon.vue';
import { lt } from './copy';
</script>

<template>
    <section id="faq" class="scroll-mt-20 bg-[#f0f0f0] py-16 sm:py-24 lg:py-[120px]">
        <div class="mx-auto grid max-w-[1060px] gap-10 px-5 lg:grid-cols-[1fr_520px] lg:gap-[90px]">
            <h2 class="text-balance text-[2.5rem] font-bold leading-[1.15] tracking-tight sm:text-[3.5rem]">{{ lt('faqTitle') }}</h2>
            <div class="space-y-4">
                <details v-for="([q, a], i) in lt('faq')" :key="i" class="faq group rounded-[4px] bg-white">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 text-lg [&::-webkit-details-marker]:hidden">
                        <span>{{ q }}</span>
                        <span class="grid size-[30px] shrink-0 place-items-center rounded-full border border-ink transition-transform group-open:rotate-45"><Icon name="plus" :size="18" /></span>
                    </summary>
                    <p class="px-5 pb-5 text-base leading-7 text-ink/80">{{ a }}</p>
                </details>
            </div>
        </div>
    </section>
</template>
