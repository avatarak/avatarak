<script setup>
import { onMounted } from 'vue';
import { state, load, toast } from './store';
import { applyDir, t } from './i18n';
import Landing from './components/Landing.vue';
import Panel from './components/Panel.vue';
import AdminGate from './components/admin/AdminGate.vue';
import AnnouncementBar from './components/AnnouncementBar.vue';

const isAdmin = location.pathname === '/admin' || location.pathname.startsWith('/admin/');

applyDir();
onMounted(async () => {
    await load();
    // returning from "Sign in with GitHub": /?verify=github-ok | github-<error>
    const v = new URLSearchParams(location.search).get('verify');
    const bill = new URLSearchParams(location.search).get('billing');
    if (bill) {
        toast(bill === 'ok' ? t('plOk') : t('plFail'));
        history.replaceState(null, '', location.pathname);
    }
    if (v?.startsWith('linkedin-')) {
        toast(v === 'linkedin-ok' ? t('vLiOk') : t('vLiFail'));
        history.replaceState(null, '', location.pathname);
    }
    if (v?.startsWith('github-')) {
        toast(v === 'github-ok' ? t('vGhOk') : t('vGhFail'));
        history.replaceState(null, '', location.pathname);
    }
});
</script>

<template>
    <div v-if="!state.ready" class="grid min-h-screen place-items-center text-muted">{{ t('loading') }}</div>
    <AdminGate v-else-if="isAdmin" />
    <template v-else>
        <div v-if="state.account" class="flex h-dvh flex-col">
            <AnnouncementBar class="shrink-0" />
            <div class="min-h-0 flex-1"><Panel /></div>
        </div>
        <template v-else>
            <AnnouncementBar />
            <Landing />
        </template>
    </template>

    <Transition enter-active-class="transition duration-150" enter-from-class="opacity-0 translate-y-2"
                leave-active-class="transition duration-150" leave-to-class="opacity-0">
        <div v-if="state.toast" class="fixed inset-x-0 bottom-6 z-50 mx-auto w-fit rounded-lg bg-ink px-4 py-2 text-sm text-white shadow-card" role="status">
            {{ state.toast }}
        </div>
    </Transition>
</template>
