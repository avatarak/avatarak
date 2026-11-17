<script setup>
import { state } from '../../store';
import { useSubmit } from '../../composables';
import { t } from '../../i18n';
import SaveBar from '../SaveBar.vue';

const { busy, message, run, err } = useSubmit('PATCH', '/account/profile', () => {
    const d = state.draft;
    return { username: d.username, name_fa: d.name_fa || null, name_en: d.name_en || null, bio: d.bio || null, website: d.website || null };
});
</script>

<template>
    <form @submit.prevent="run">
        <h2 class="font-display text-2xl font-bold">{{ t('editProfile') }}</h2>
        <div class="mt-6 space-y-5">
            <div>
                <label class="label" for="p-fa">{{ t('displayNameFa') }}</label>
                <input id="p-fa" v-model="state.draft.name_fa" class="field" maxlength="100">
                <p v-if="err('name_fa')" class="mt-1 text-sm text-red-600">{{ err('name_fa') }}</p>
            </div>
            <div>
                <label class="label" for="p-en">{{ t('displayNameEn') }}</label>
                <input id="p-en" v-model="state.draft.name_en" dir="ltr" class="field" maxlength="100">
            </div>
            <div>
                <label class="label" for="p-user">{{ t('username') }}</label>
                <input id="p-user" v-model="state.draft.username" dir="ltr" class="field">
                <p v-if="err('username')" class="mt-1 text-sm text-red-600">{{ err('username') }}</p>
                <p v-else class="hint mt-1">{{ t('usernameHint') }}: <bdi dir="ltr" class="inline-block">/u/{{ state.draft.username }}</bdi></p>
            </div>
            <div>
                <label class="label" for="p-bio">{{ t('bio') }}</label>
                <textarea id="p-bio" v-model="state.draft.bio" class="field" rows="4" maxlength="500"></textarea>
                <p class="hint mt-1">{{ (state.draft.bio || '').length }}/500</p>
            </div>
            <div>
                <label class="label" for="p-web">{{ t('website') }}</label>
                <input id="p-web" v-model="state.draft.website" dir="ltr" class="field" placeholder="https://" inputmode="url">
                <p v-if="err('website')" class="mt-1 text-sm text-red-600">{{ err('website') }}</p>
            </div>
        </div>
        <SaveBar :busy="busy" :error="message" />
    </form>
</template>
