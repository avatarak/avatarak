<script setup>
import { ref } from 'vue';
import AuthCard from './AuthCard.vue';
import LandingHeader from './landing/LandingHeader.vue';
import LandingHero from './landing/LandingHero.vue';
import FeatureRow from './landing/FeatureRow.vue';
import MockApps from './landing/MockApps.vue';
import MockProfile from './landing/MockProfile.vue';
import MockVerified from './landing/MockVerified.vue';
import MockIdentities from './landing/MockIdentities.vue';
import MockPrivacy from './landing/MockPrivacy.vue';
import DevSection from './landing/DevSection.vue';
import FaqSection from './landing/FaqSection.vue';
import FinalCta from './landing/FinalCta.vue';
import LandingFooter from './landing/LandingFooter.vue';
import Icon from './Icon.vue';
import { lt } from './landing/copy';

const auth = ref('register');
const authOpen = ref(false);
const email = ref('');
const show = (mode, prefill) => { auth.value = mode; if (typeof prefill === 'string') email.value = prefill; authOpen.value = true; };
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <LandingHeader @login="show('login')" @register="show('register')" />
        <main>
            <LandingHero @start="(e) => show('register', e)" />

            <!-- "at a glance" strip: honest replacement for a customers row -->
            <section class="bg-white px-5 pb-6 pt-14">
                <p class="text-center text-sm font-medium uppercase tracking-[0.04em] text-[#a7aaad]">{{ lt('stripLabel') }}</p>
                <ul class="mx-auto mt-8 flex max-w-[1060px] flex-wrap justify-center gap-x-14 gap-y-6 text-[#7a7f85]">
                    <li v-for="[icon, label] in lt('strip')" :key="icon" class="flex flex-col items-center gap-2 text-sm"><Icon :name="icon" :size="28" />{{ label }}</li>
                </ul>
            </section>

            <FeatureRow id="sync" :title="lt('syncTitle')" :text="lt('syncText')"><MockApps /></FeatureRow>
            <FeatureRow id="profile" tone="warm" reverse :title="lt('profTitle')" :text="lt('profText')" :cta="lt('profCta')" @cta="show('register')"><MockProfile /></FeatureRow>
            <FeatureRow id="verified" :title="lt('verTitle')" :text="lt('verText')"><MockVerified /></FeatureRow>
            <FeatureRow id="identities" tone="gray" :title="lt('idTitle')" :text="lt('idText')"><MockIdentities /></FeatureRow>
            <FeatureRow id="privacy" reverse :title="lt('privTitle')" :text="lt('privText')"><MockPrivacy /></FeatureRow>
            <DevSection @cta="show('register')" />
            <FaqSection />
            <FinalCta @start="show('register')" />
        </main>
        <LandingFooter @login="show('login')" @register="show('register')" />

        <AuthCard :open="authOpen" :mode="auth" :email="email" @close="authOpen = false" @switch="auth = $event" />
    </div>
</template>
