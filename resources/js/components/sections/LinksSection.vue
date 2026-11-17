<script setup>
import { state } from '../../store';
import { useSubmit } from '../../composables';
import { t } from '../../i18n';
import SaveBar from '../SaveBar.vue';

const NETWORKS = ['telegram', 'instagram', 'x', 'github', 'linkedin', 'eitaa', 'rubika'];
state.draft.socials = { ...(state.draft.socials || {}) };

const { busy, message, run, err } = useSubmit('PATCH', '/account/profile', () => ({ socials: state.draft.socials }));
</script>

<template>
    <form @submit.prevent="run">
        <h2 class="font-display text-2xl font-bold">{{ t('linksTitle') }}</h2>
        <p class="hint mt-2">{{ t('linksHint') }}</p>
        <div class="mt-6 space-y-4">
            <div v-for="n in NETWORKS" :key="n">
                <label class="label" :for="'s-' + n">{{ n }}</label>
                <input :id="'s-' + n" v-model="state.draft.socials[n]" dir="ltr" class="field" placeholder="username">
                <p v-if="err('socials.' + n)" class="mt-1 text-sm text-red-600">{{ err('socials.' + n) }}</p>
            </div>
        </div>
        <SaveBar :busy="busy" :error="message" />
    </form>
</template>
