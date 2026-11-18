<script setup>
import { state } from '../../store';
import { useSubmit } from '../../composables';
import { t } from '../../i18n';
import SaveBar from '../SaveBar.vue';
import LangTabs from '../LangTabs.vue';
import { k, dirOf } from '../../lang';

const { busy, message, run, err } = useSubmit('PATCH', '/account/profile', () => {
    const d = state.draft;
    return { username: d.username, name_fa: d.name_fa || null, name_en: d.name_en || null, headline: d.headline || null,
             location: d.location || null, bio: d.bio || null, headline_alt: d.headline_alt || null, location_alt: d.location_alt || null, bio_alt: d.bio_alt || null, website: d.website || null };
});
</script>

<template>
    <form class="space-y-5" @submit.prevent="run">
        <LangTabs />
        <div>
            <label class="label" for="p-fa">{{ t('displayNameFa') }}</label>
            <input id="p-fa" v-model="state.draft.name_fa" class="field" maxlength="100">
            <p v-if="err('name_fa')" class="mt-1 text-sm text-red-600">{{ err('name_fa') }}</p>
        </div>
        <div>
            <label class="label" for="p-en">{{ t('displayNameEn') }}</label>
            <input id="p-en" v-model="state.draft.name_en" dir="ltr" class="field text-start" maxlength="100">
        </div>
        <div>
            <label class="label" for="p-head">{{ t('headline') }}</label>
            <input id="p-head" v-model="state.draft[k('headline')]" :dir="dirOf()" class="field text-start" maxlength="120">
            <p v-if="err('headline')" class="mt-1 text-sm text-red-600">{{ err('headline') }}</p>
        </div>
        <div>
            <label class="label" for="p-loc">{{ t('location') }}</label>
            <input id="p-loc" v-model="state.draft[k('location')]" :dir="dirOf()" class="field text-start" maxlength="100">
        </div>
        <div>
            <label class="label" for="p-bio">{{ t('bio') }}</label>
            <textarea id="p-bio" v-model="state.draft[k('bio')]" :dir="dirOf()" class="field text-start" rows="5" maxlength="500"></textarea>
            <p class="hint mt-1 text-end" dir="ltr">{{ (state.draft[k('bio')] || '').length }}/500</p>
        </div>
        <div>
            <label class="label" for="p-web">{{ t('website') }}</label>
            <input id="p-web" v-model="state.draft.website" dir="ltr" class="field text-start" placeholder="https://" inputmode="url">
            <p v-if="err('website')" class="mt-1 text-sm text-red-600">{{ err('website') }}</p>
        </div>
        <div>
            <label class="label" for="p-user">{{ t('username') }}</label>
            <input id="p-user" v-model="state.draft.username" dir="ltr" class="field text-start">
            <p v-if="err('username')" class="mt-1 text-sm text-red-600">{{ err('username') }}</p>
            <p v-else class="hint mt-1">{{ t('usernameHint') }}: <bdi dir="ltr" class="inline-block">/{{ state.draft.username }}</bdi></p>
        </div>
        <SaveBar :busy="busy" :error="message" />
    </form>
</template>
