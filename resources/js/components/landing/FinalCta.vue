<script setup>
import Icon from '../Icon.vue';
import Person from './Person.vue';
import { lt } from './copy';

defineEmits(['start']);
</script>

<template>
    <section class="bg-white py-16 sm:py-24 lg:py-[120px]">
        <div class="mx-auto grid max-w-[1060px] items-center gap-12 px-5 lg:grid-cols-2">
            <div>
                <h2 class="max-w-[28rem] text-balance text-[2.5rem] font-bold leading-[1.15] tracking-tight sm:text-[3.5rem]">{{ lt('ctaTitle') }}</h2>
                <button type="button" class="btn btn-primary mt-9 !min-h-[56px] !px-6" @click="$emit('start')">{{ lt('ctaBtn') }} <Icon name="chevron" :size="16" flip /></button>
            </div>
            <div class="mx-auto grid w-full max-w-[28rem] grid-cols-3 gap-3 sm:gap-[30px] lg:max-w-none" aria-hidden="true">
                <template v-for="n in 9" :key="n">
                    <span v-if="n === 5" class="grid aspect-square w-full max-w-[7.5rem] place-items-center rounded-full border-[1.5px] border-brand text-brand"><Icon name="plus" :size="28" /></span>
                    <Person v-else :tone="n" :size="120" class="h-auto w-full max-w-[7.5rem]" />
                </template>
            </div>
        </div>
    </section>
</template>
