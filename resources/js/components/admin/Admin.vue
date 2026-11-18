<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { api } from '../../api';
import { state, setAccount } from '../../store';
import { t, i18n } from '../../i18n';
import { adm, route, parseHash, go } from '../../admin/store';
import { adminApi } from '../../admin/lib';
import Icon from '../Icon.vue';
import Logo from '../Logo.vue';
import LangSwitch from '../LangSwitch.vue';
import InitialsAvatar from '../InitialsAvatar.vue';
import Dashboard from './Dashboard.vue';
import Users from './Users.vue';
import Reports from './Reports.vue';
import Sites from './Sites.vue';
import Verified from './Verified.vue';
import Audit from './Audit.vue';
import Payments from './Payments.vue';

const PAGES = { dashboard: Dashboard, users: Users, reports: Reports, sites: Sites, verified: Verified, payments: Payments, audit: Audit };
const NAV = [['dashboard', 'dashboard'], ['users', 'users'], ['reports', 'flag'], ['sites', 'key'], ['verified', 'verified'], ['payments', 'gift'], ['audit', 'history']];

const menu = ref(false);
const page = computed(() => (PAGES[route.page] ? route.page : 'dashboard'));

function onHash() { parseHash(); menu.value = false; }
onMounted(async () => {
    parseHash();
    window.addEventListener('hashchange', onHash);
    if (!adm.counts) { try { adm.counts = (await adminApi('GET', '/overview')).counts; } catch { /* the dashboard shows its own error */ } }
});
onBeforeUnmount(() => window.removeEventListener('hashchange', onHash));
watch(() => i18n.locale, () => { document.title = `${t('a_title')} · ${t('a_nav_' + page.value)}`; }, { immediate: true });
watch(page, () => { document.title = `${t('a_title')} · ${t('a_nav_' + page.value)}`; window.scrollTo(0, 0); });

async function logout() { await api('POST', '/logout'); setAccount(null); location.href = '/'; }
const reportsBadge = computed(() => adm.counts?.reports_open || 0);
</script>

<template>
    <div class="min-h-dvh bg-soft text-ink">
        <!-- sidebar -->
        <div v-if="menu" class="fixed inset-0 z-30 bg-ink/40 lg:hidden" @click="menu = false"></div>
        <aside class="fixed inset-y-0 start-0 z-40 flex w-64 flex-col border-e border-line bg-white transition-transform lg:translate-x-0"
               :class="menu ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full lg:translate-x-0 lg:rtl:translate-x-0'" :aria-label="t('a_menu')">
            <div class="flex h-16 items-center gap-2 border-b border-line px-5">
                <a href="/" aria-label="home"><Logo /></a>
                <span class="rounded-[4px] bg-ink px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide text-white">{{ t('a_title') }}</span>
            </div>
            <nav class="flex-1 space-y-0.5 overflow-y-auto p-3">
                <a v-for="[id, icon] in NAV" :key="id" :href="`#/${id}`" class="flex items-center gap-3 rounded-[6px] px-3 py-2.5 text-[0.9375rem] font-medium transition-colors"
                   :class="page === id ? 'bg-brand/10 text-brand' : 'text-ink-2 hover:bg-soft'" :aria-current="page === id ? 'page' : null">
                    <Icon :name="icon" :size="20" />
                    <span class="flex-1">{{ t('a_nav_' + id) }}</span>
                    <span v-if="id === 'reports' && reportsBadge" class="min-w-[1.4rem] rounded-full bg-amber-500 px-1.5 text-center text-xs font-bold leading-5 text-white tabular-nums">{{ reportsBadge }}</span>
                </a>
            </nav>
            <div class="border-t border-line p-3">
                <a href="/" class="flex items-center gap-3 rounded-[6px] px-3 py-2.5 text-sm text-ink-2 hover:bg-soft"><Icon name="back" :size="18" flip /> {{ t('a_back') }}</a>
            </div>
        </aside>

        <!-- main -->
        <div class="lg:ps-64">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-white/90 px-4 backdrop-blur sm:px-6">
                <button type="button" class="icon-btn lg:hidden" :aria-label="t('a_menu')" :aria-expanded="menu" @click="menu = !menu"><Icon name="menu" :size="22" /></button>
                <h1 class="text-lg font-bold">{{ t('a_nav_' + page) }}</h1>
                <div class="ms-auto flex items-center gap-2">
                    <LangSwitch />
                    <div class="hidden items-center gap-2 sm:flex">
                        <span class="size-8 overflow-hidden rounded-full"><InitialsAvatar :name="state.account.email" :seed="state.account.profile.username" /></span>
                        <span dir="ltr" class="max-w-[12rem] truncate text-sm text-muted">{{ state.account.email }}</span>
                    </div>
                    <button type="button" class="icon-btn" :title="t('logout')" :aria-label="t('logout')" @click="logout"><Icon name="logout" :size="20" /></button>
                </div>
            </header>
            <main class="mx-auto max-w-[1280px] p-4 sm:p-6">
                <component :is="PAGES[page]" :key="page" />
            </main>
        </div>
    </div>
</template>
