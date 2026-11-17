<script setup>
import Icon from '../Icon.vue';

/** One alternating text + visual section, measured from gravatar.com (450px text column, 120px vertical padding). */
defineProps({ id: String, title: String, text: String, cta: String, reverse: Boolean, tone: { type: String, default: 'white' } });
defineEmits(['cta']);
</script>

<template>
    <section :id="id" class="scroll-mt-20 py-16 sm:py-24 lg:py-[120px]"
             :class="{ 'bg-white': tone === 'white', 'bg-[#f6f7f7]': tone === 'gray', 'landing-warm': tone === 'warm' }">
        <div class="mx-auto grid max-w-[1060px] items-center gap-12 px-5 lg:grid-cols-2 lg:gap-[90px]">
            <div :class="reverse ? 'lg:order-2' : ''">
                <h2 class="text-balance text-[2.5rem] font-bold leading-[1.15] tracking-tight sm:text-[3.5rem]">{{ title }}</h2>
                <p class="mt-6 max-w-[27rem] text-lg leading-[1.55]">{{ text }}</p>
                <button v-if="cta" type="button" class="btn btn-primary mt-9 !min-h-[56px] !px-6" @click="$emit('cta')">{{ cta }} <Icon name="chevron" :size="16" flip /></button>
            </div>
            <div :class="reverse ? 'lg:order-1' : ''"><slot /></div>
        </div>
    </section>
</template>
