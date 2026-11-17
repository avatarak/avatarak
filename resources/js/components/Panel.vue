<script setup>
import { ref, computed, markRaw } from 'vue';
import { api } from '../api';
import { state, setAccount } from '../store';
import { t } from '../i18n';
import Logo from './Logo.vue';
import LangSwitch from './LangSwitch.vue';
import ProfilePreview from './ProfilePreview.vue';
import ProfileSection from './sections/ProfileSection.vue';
import AvatarsSection from './sections/AvatarsSection.vue';
import EmailsSection from './sections/EmailsSection.vue';
import LinksSection from './sections/LinksSection.vue';
import AppearanceSection from './sections/AppearanceSection.vue';
import PrivacySection from './sections/PrivacySection.vue';
import DevelopersSection from './sections/DevelopersSection.vue';

const NAV = [
    { id: 'profile', c: markRaw(ProfileSection), preview: true },
    { id: 'avatars', c: markRaw(AvatarsSection), preview: true },
    { id: 'emails', c: markRaw(EmailsSection) },
    { id: 'links', c: markRaw(LinksSection), preview: true },
    { id: 'appearance', c: markRaw(AppearanceSection), preview: true },
    { id: 'privacy', c: markRaw(PrivacySection) },
    { id: 'developers', c: markRaw(DevelopersSection) },
];

const current = ref('profile');
const active = computed(() => NAV.find((n) => n.id === current.value));
const avatarSrc = computed(() => state.account.profile.has_avatar ? `/account/avatar?v=${state.account.profile.avatar_version}` : null);

async function logout() {
    await api('POST', '/logout');
    setAccount(null);
}
</script>

<template>
    <div class="mx-auto grid min-h-screen max-w-6xl gap-x-8 px-4 lg:grid-cols-[14rem_1fr]">
        <aside class="py-6 lg:sticky lg:top-0 lg:h-screen">
            <div class="flex items-center justify-between lg:block">
                <Logo />
                <div class="flex gap-2 lg:mt-4"><LangSwitch /><button class="btn btn-ghost !py-1.5 text-sm lg:hidden" @click="logout">{{ t('logout') }}</button></div>
            </div>
            <p class="mt-6 hidden text-xs font-medium text-muted lg:block">{{ t('panelTitle') }}</p>
            <nav class="-mx-1 mt-3 flex gap-1 overflow-x-auto px-1 pb-1 lg:flex-col lg:overflow-visible" aria-label="sections">
                <button v-for="n in NAV" :key="n.id" type="button"
                        class="whitespace-nowrap rounded-lg px-3 py-2 text-start transition-colors"
                        :class="current === n.id ? 'bg-surface font-medium shadow-card ring-1 ring-line' : 'text-muted hover:bg-surface/70 hover:text-ink'"
                        :aria-current="current === n.id ? 'page' : undefined" @click="current = n.id">
                    {{ t('nav_' + n.id) }}
                </button>
            </nav>
            <button class="btn btn-ghost mt-6 hidden w-full text-sm lg:flex" @click="logout">{{ t('logout') }}</button>
        </aside>

        <div class="grid items-start gap-8 py-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <main class="card min-w-0 p-6 sm:p-8">
                <component :is="active.c" :key="current" />
            </main>
            <aside v-if="active.preview" class="xl:sticky xl:top-6">
                <h2 class="mb-3 text-sm font-medium text-muted">{{ t('preview') }}</h2>
                <ProfilePreview :profile="state.draft" :avatar-src="avatarSrc" />
                <a class="mt-3 block text-center text-sm text-brand hover:underline" :href="`/u/${state.account.profile.username}`" target="_blank" rel="noopener">{{ t('viewPublic') }}</a>
            </aside>
        </div>
    </div>
</template>
