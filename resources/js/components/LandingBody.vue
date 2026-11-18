<script setup>
// Everything below the first screen of the home page. It is loaded and attached only when the visitor scrolls near it
// (see Landing.vue), so the first screen becomes interactive sooner.
import FeatureRow from './landing/FeatureRow.vue';
import MockApps from './landing/MockApps.vue';
import MockProfile from './landing/MockProfile.vue';
import MockVerified from './landing/MockVerified.vue';
import MockIdentities from './landing/MockIdentities.vue';
import MockPrivacy from './landing/MockPrivacy.vue';
import DevSection from './landing/DevSection.vue';
import FaqSection from './landing/FaqSection.vue';
import PricingSection from './landing/PricingSection.vue';
import FinalCta from './landing/FinalCta.vue';
import Icon from './Icon.vue';
import { lt } from './landing/copy';

const emit = defineEmits(['register']);
const show = () => emit('register');
</script>

<template>
    <div class="contents">
    <!-- "at a glance" strip: honest replacement for a customers row -->
    <section class="bg-white px-5 pb-6 pt-14">
        <p class="text-center text-sm font-medium uppercase tracking-[0.04em] text-[#a7aaad]">{{ lt('stripLabel') }}</p>
        <ul class="mx-auto mt-8 flex max-w-[1060px] flex-wrap justify-center gap-x-14 gap-y-6 text-[#7a7f85]">
            <li v-for="[icon, label] in lt('strip')" :key="icon" class="flex flex-col items-center gap-2 text-sm"><Icon :name="icon" :size="28" />{{ label }}</li>
        </ul>
    </section>

    <FeatureRow id="sync" :title="lt('syncTitle')" :text="lt('syncText')"><MockApps /></FeatureRow>
    <FeatureRow id="profile" tone="warm" reverse :title="lt('profTitle')" :text="lt('profText')" :cta="lt('profCta')" @cta="show()"><MockProfile /></FeatureRow>
    <FeatureRow id="verified" :title="lt('verTitle')" :text="lt('verText')"><MockVerified /></FeatureRow>
    <FeatureRow id="identities" tone="gray" :title="lt('idTitle')" :text="lt('idText')"><MockIdentities /></FeatureRow>
    <FeatureRow id="privacy" reverse :title="lt('privTitle')" :text="lt('privText')"><MockPrivacy /></FeatureRow>
    <DevSection @cta="show()" />
    <PricingSection @start="show()" />
    <FaqSection />
    <FinalCta @start="show()" />
    </div>
</template>
