<script setup>
import { lt } from './copy';
import Person from './Person.vue';
// Three generic "apps" showing the same avatar: nothing here imitates a real third-party product.
</script>

<template>
    <div class="relative mx-auto h-[26rem] w-full max-w-[34rem]" aria-hidden="true">
        <!-- forum -->
        <div class="absolute start-0 top-0 w-[66%] overflow-hidden rounded-[6px] bg-white shadow-card">
            <div class="flex h-9 items-center gap-2 bg-brand px-3 text-xs font-semibold text-white"><span class="size-2 rounded-full bg-white/70"></span>{{ lt('winForum') }}</div>
            <div class="space-y-3 bg-[#eef2fc] p-3">
                <div v-for="n in 3" :key="n" class="flex gap-2 rounded-[4px] bg-white p-2.5">
                    <Person :tone="n + 1" :size="26" /><span class="mt-1 block space-y-1.5"><span class="block h-1.5 w-24 max-w-full rounded bg-line sm:w-32"></span><span class="block h-1.5 w-16 rounded bg-line sm:w-20"></span></span>
                </div>
            </div>
        </div>
        <!-- profile -->
        <div class="absolute end-0 top-24 w-[60%] overflow-hidden rounded-[6px] bg-white shadow-card">
            <div class="flex h-9 items-center gap-2 bg-[#101517] px-3 text-xs font-semibold text-white"><span class="size-2 rounded-full bg-white/50"></span>{{ lt('winProfile') }}</div>
            <div class="flex gap-4 p-4">
                <Person :tone="0" :size="84" />
                <div class="grid flex-1 grid-cols-2 gap-2"><span class="h-12 rounded bg-soft"></span><span class="h-12 rounded bg-soft"></span><span class="h-12 rounded bg-soft"></span><span class="h-12 rounded bg-soft"></span></div>
            </div>
        </div>
        <!-- chat -->
        <div class="absolute bottom-0 start-[8%] w-[72%] overflow-hidden rounded-[6px] bg-white shadow-card">
            <div class="flex h-9 items-center gap-2 bg-[#4a154b] px-3 text-xs font-semibold text-white"><span class="size-2 rounded-full bg-white/60"></span>{{ lt('winChat') }}</div>
            <div class="flex">
                <div class="w-20 bg-[#3d1140] p-2"><span class="mb-2 block h-1.5 w-10 rounded bg-white/40"></span><span class="mb-2 block h-1.5 w-12 rounded bg-white/25"></span><span class="block h-1.5 w-8 rounded bg-white/25"></span></div>
                <div class="flex-1 space-y-3 p-3">
                    <div v-for="n in 3" :key="n" class="flex gap-2"><Person :tone="n === 2 ? 0 : n + 2" :size="26" :ring="n === 2" /><span class="mt-1 block space-y-1.5"><span class="block h-1.5 w-28 rounded bg-line"></span><span class="block h-1.5 w-16 rounded bg-line"></span></span></div>
                </div>
            </div>
        </div>
    </div>
</template>
