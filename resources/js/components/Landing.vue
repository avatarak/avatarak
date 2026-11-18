<script setup>
import { ref, watch, onMounted, defineAsyncComponent, hydrateOnVisible } from 'vue';
import { state } from '../store';
import AuthCard from './AuthCard.vue';
import LandingHeader from './landing/LandingHeader.vue';
import LandingHero from './landing/LandingHero.vue';

// Below the first screen: pre-rendered in the HTML (so it is readable and searchable at once), but its code is fetched and
// attached only when the visitor scrolls close to it.
const lazy = (loader) => defineAsyncComponent({ loader, hydrate: hydrateOnVisible({ rootMargin: '400px' }) });
const LandingBody = lazy(() => import('./LandingBody.vue'));
const LandingFooter = lazy(() => import('./landing/LandingFooter.vue'));

const props = defineProps({ askLogin: Boolean });       // /panel while signed out: ask to sign in
const auth = ref(props.askLogin ? 'login' : 'register');
const authOpen = ref(props.askLogin);
const email = ref('');
const mounted = ref(false);        // the sign-in dialog lives outside the page (a Teleport), so it is added after the pre-rendered page is attached
onMounted(() => { mounted.value = true; });
const show = (mode, prefill) => {
    if (state.account) { location.href = '/panel'; return; }       // already signed in: every call to action leads to the dashboard
    auth.value = mode;
    if (typeof prefill === 'string') email.value = prefill;
    authOpen.value = true;
};
watch(() => state.account, (a) => { if (a && authOpen.value) location.href = '/panel'; });
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <LandingHeader @login="show('login')" @register="show('register')" />
        <main>
            <LandingHero @start="(e) => show('register', e)" />

            <LandingBody @register="show('register')" />
        </main>
        <LandingFooter @login="show('login')" @register="show('register')" />

        <AuthCard v-if="mounted" :open="authOpen" :mode="auth" :email="email" @close="authOpen = false" @switch="auth = $event" />
    </div>
</template>
