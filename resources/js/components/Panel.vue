<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { api } from '../api';
import { state, setAccount, toast } from '../store';
import { t, i18n } from '../i18n';
import { copyText } from '../composables';
import Logo from './Logo.vue';
import Icon from './Icon.vue';
import InitialsAvatar from './InitialsAvatar.vue';
import LangSwitch from './LangSwitch.vue';
import ProfileCard from './ProfileCard.vue';
import AvatarsSection from './sections/AvatarsSection.vue';
import AboutSection from './sections/AboutSection.vue';
import LinksSection from './sections/LinksSection.vue';
import EmailsSection from './sections/EmailsSection.vue';
import DesignSection from './sections/DesignSection.vue';
import PrivacySection from './sections/PrivacySection.vue';
import DevelopersSection from './sections/DevelopersSection.vue';
import VerifiedSection from './sections/VerifiedSection.vue';
import SecuritySection from './sections/SecuritySection.vue';
import ShareSection from './sections/ShareSection.vue';
import StatsSection from './sections/StatsSection.vue';
import BillingSection from './sections/BillingSection.vue';
import DomainSection from './sections/DomainSection.vue';
import GallerySection from './sections/GallerySection.vue';

const SECTIONS = {
    avatars: AvatarsSection, about: AboutSection, links: LinksSection, verified: VerifiedSection, emails: EmailsSection,
    share: ShareSection, stats: StatsSection, billing: BillingSection, domain: DomainSection, gallery: GallerySection, design: DesignSection, privacy: PrivacySection, security: SecuritySection, developers: DevelopersSection,
};
const GROUPS = [
    [['avatars', null], ['about', 'user'], ['links', 'link'], ['gallery', 'image'], ['verified', 'verified'], ['emails', 'mail']],
    [['stats', 'chart'], ['share', 'qr'], ['design', 'palette'], ['privacy', 'shield'], ['security', 'lock']],
    [['developers', 'code'], ['domain', 'globe'], ['billing', 'gift']],
];

const open = ref(null);        // null = sidebar list
const mobilePreview = ref(false);
const menu = ref(false);
const menuRoot = ref(null);

const profile = computed(() => state.account.profile);
const avatarSrc = computed(() => (profile.value.has_avatar ? `/account/avatar?v=${profile.value.avatar_version}` : null));
const verified = computed(() => state.account.emails.some((e) => e.verified));
const publicUrl = computed(() => `${window.location.origin}/${profile.value.username}`);
const publicPath = computed(() => `${window.location.host}/${profile.value.username}`);

async function logout() {
    await api('POST', '/logout');
    setAccount(null);
}
async function copyUrl() { await copyText(publicUrl.value); toast(t('linkCopied')); }
function outside(e) { if (menu.value && !menuRoot.value?.contains(e.target)) menu.value = false; }
onMounted(() => document.addEventListener('click', outside));
onBeforeUnmount(() => document.removeEventListener('click', outside));
</script>

<template>
    <div class="flex h-full flex-col bg-white">
        <header class="flex h-[70px] shrink-0 items-center justify-between px-5">
            <Logo />
            <div class="flex items-center gap-1">
                <a :href="`/${profile.username}`" target="_blank" rel="noopener" class="icon-btn" :title="t('viewPublic')" :aria-label="t('viewPublic')">
                    <Icon name="external" :size="20" />
                </a>
                <LangSwitch />
                <div ref="menuRoot" class="relative ms-1">
                    <button type="button" class="flex items-center gap-1 rounded-full p-1 hover:bg-soft" :aria-expanded="menu" :aria-label="t('menu')" @click="menu = !menu">
                        <img v-if="avatarSrc" :src="avatarSrc" alt="" class="size-9 rounded-full object-cover">
                        <span v-else class="size-9 overflow-hidden rounded-full"><InitialsAvatar :name="profile.name_fa || profile.name_en || profile.username" :seed="profile.username" /></span>
                    </button>
                    <div v-if="menu" class="card absolute end-0 top-full z-30 mt-2 w-56 overflow-hidden py-1">
                        <div dir="ltr" class="truncate border-b border-line px-4 py-2 text-start text-sm text-muted">@{{ profile.username }}</div>
                        <a v-if="state.account.is_admin" href="/admin" class="flex w-full items-center gap-3 px-4 py-2.5 text-start hover:bg-soft"><Icon name="dashboard" :size="18" /> {{ t('a_title') }}</a>
                        <button type="button" class="flex w-full items-center gap-3 px-4 py-2.5 text-start hover:bg-soft" @click="logout">
                            <Icon name="logout" :size="18" /> {{ t('logout') }}
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex min-h-0 flex-1">
            <aside class="relative flex min-h-0 w-full flex-col lg:w-[26rem] lg:shrink-0" :class="mobilePreview ? 'hidden lg:flex' : 'flex'">
                <div class="flex-1 overflow-y-auto px-5 pb-24 lg:pb-6">
                    <div v-if="!verified" class="mt-3 rounded-[8px] border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-900" role="status">
                        <p>{{ t('verifyNotice') }}</p>
                        <button v-if="open !== 'emails'" type="button" class="mt-2 font-semibold underline underline-offset-2" @click="open = 'emails'">{{ t('verifyAction') }}</button>
                    </div>
                    <!-- section list -->
                    <nav v-if="!open" :aria-label="t('menu')" class="pt-4">
                        <ul v-for="(group, gi) in GROUPS" :key="gi" class="py-2" :class="gi ? 'mt-2' : ''">
                            <li v-for="[id, icon] in group" :key="id">
                                <button type="button" class="flex w-full items-center gap-3.5 rounded-[6px] px-2 py-2.5 text-start text-[0.9375rem] transition-colors hover:bg-soft" @click="open = id">
                                    <img v-if="id === 'avatars' && avatarSrc" :src="avatarSrc" alt="" class="size-5 rounded-full object-cover">
                                    <span v-else-if="id === 'avatars'" class="size-5 rounded-full bg-line"></span>
                                    <Icon v-else :name="icon" :size="20" />
                                    <span class="flex-1">{{ t('nav_' + id) }}</span>
                                    <Icon name="chevron" :size="16" flip class="text-muted" />
                                </button>
                            </li>
                        </ul>
                    </nav>

                    <!-- one section -->
                    <div v-else class="pt-2">
                        <button type="button" class="icon-btn !bg-soft" :aria-label="t('back')" @click="open = null"><Icon name="back" :size="20" flip /></button>
                        <h1 class="mt-5 text-xl font-bold">{{ t('nav_' + open) }}</h1>
                        <p class="hint mt-1">{{ t('d_' + open) }}</p>
                        <div class="mt-6"><component :is="SECTIONS[open]" :key="open" @goto="open = $event" /></div>
                    </div>
                </div>

                <div v-if="!open" class="shrink-0 px-5 pb-5">
                    <div class="flex items-center justify-between gap-2 rounded-[8px] border border-line bg-white py-2 ps-4 pe-2 shadow-card">
                        <span dir="ltr" class="min-w-0 truncate text-sm text-muted" :title="t('yourAddress')">{{ publicPath }}</span>
                        <button type="button" class="icon-btn shrink-0" :title="t('copy')" :aria-label="t('copy')" @click="copyUrl"><Icon name="share" :size="18" /></button>
                    </div>
                </div>
            </aside>

            <main class="min-w-0 flex-1 overflow-y-auto rounded-ss-[20px]" :class="mobilePreview ? 'block' : 'hidden lg:block'" :aria-label="t('preview')">
                <ProfileCard :profile="state.draft" :avatar-src="avatarSrc" :verified="verified" :verified-accounts="state.account.verified_accounts || []" :gallery="state.account.gallery || []" />
            </main>
        </div>

        <button type="button" class="btn btn-primary fixed inset-x-0 bottom-5 z-20 mx-auto w-fit shadow-card lg:hidden" @click="mobilePreview = !mobilePreview">
            {{ mobilePreview ? t('back') : t('preview') }}
        </button>
    </div>
</template>
