<script setup>
import { ref } from 'vue';
import { state } from '../../store';
import { t } from '../../i18n';
import AuthCard from '../AuthCard.vue';
import Logo from '../Logo.vue';
import LangSwitch from '../LangSwitch.vue';
import Admin from './Admin.vue';

const open = ref(true);
</script>

<template>
    <Admin v-if="state.account?.is_admin" />
    <div v-else class="grid min-h-dvh place-items-center bg-soft p-6">
        <div class="card w-full max-w-sm p-8 text-center">
            <div class="mb-5 flex justify-center"><Logo /></div>
            <template v-if="!state.account">
                <p class="text-muted">{{ t('a_login_required') }}</p>
                <button type="button" class="btn btn-primary mt-5 w-full" @click="open = true">{{ t('login') }}</button>
                <AuthCard :open="open" mode="login" @close="open = false" @switch="() => {}" />
            </template>
            <template v-else>
                <h1 class="text-xl font-bold">404</h1>
                <p class="mt-1 text-muted">{{ t('a_forbidden') }}</p>
                <a href="/panel" class="btn btn-ghost mt-5 w-full">{{ t('a_back') }}</a>
            </template>
            <div class="mt-5 flex justify-center"><LangSwitch /></div>
        </div>
    </div>
</template>
