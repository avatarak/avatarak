<script setup>
import { onMounted } from 'vue';
import { state, load } from './store';
import { applyDir, t } from './i18n';
import Landing from './components/Landing.vue';
import Panel from './components/Panel.vue';

applyDir();
onMounted(load);
</script>

<template>
    <div v-if="!state.ready" class="grid min-h-screen place-items-center text-muted">{{ t('loading') }}</div>
    <Panel v-else-if="state.account" />
    <Landing v-else />

    <Transition enter-active-class="transition duration-150" enter-from-class="opacity-0 translate-y-2"
                leave-active-class="transition duration-150" leave-to-class="opacity-0">
        <div v-if="state.toast" class="fixed inset-x-0 bottom-6 z-50 mx-auto w-fit rounded-lg bg-ink px-4 py-2 text-sm text-white shadow-card" role="status">
            {{ state.toast }}
        </div>
    </Transition>
</template>
