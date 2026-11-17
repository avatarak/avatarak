<script setup>
import { onBeforeUnmount } from 'vue';
import { api } from '../../api';
import { state } from '../../store';
import { useSubmit } from '../../composables';
import { t } from '../../i18n';
import { SOCIAL_NAMES } from '../../icons';
import Icon from '../Icon.vue';
import SocialIcon from '../SocialIcon.vue';
import SaveBar from '../SaveBar.vue';

state.draft.socials = { ...(state.draft.socials || {}) };
state.draft.links = [...(state.draft.links || [])].map((l) => ({ description: '', ...l }));

const add = () => state.draft.links.length < 10 && state.draft.links.push({ label: '', url: '', description: '' });
const remove = (i) => state.draft.links.splice(i, 1);

const { busy, message, run: save, err } = useSubmit('PATCH', '/account/profile', () => ({
    socials: state.draft.socials,
    links: state.draft.links.filter((l) => l.label || l.url),
    show_link_images: state.draft.show_link_images !== false,
}));

// The server fetches preview images AFTER responding. Poll briefly and fill them into the draft by URL,
// without touching anything the user is still typing.
let timer = null;
let tries = 0;
const pending = () => state.draft.links.some((l) => l.url && l.thumb === undefined);
async function poll() {
    clearTimeout(timer);
    if (!pending() || tries++ >= 8) return;
    timer = setTimeout(async () => {
        try {
            const fresh = (await api('GET', '/account')).profile.links || [];
            for (const l of state.draft.links) {
                const hit = fresh.find((f) => f.url === l.url);
                if (hit && hit.thumb !== undefined) l.thumb = hit.thumb;
            }
        } catch { /* try again next tick */ }
        poll();
    }, 1800);
}
async function run() {
    if (await save()) { state.draft.links = state.draft.links.map((l) => ({ description: '', ...l })); tries = 0; poll(); }
}
onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <form @submit.prevent="run">
        <div class="flex items-center justify-between gap-4 rounded-[8px] border border-line p-4">
            <div>
                <div class="font-medium">{{ t('showImages') }}</div>
                <div class="hint mt-0.5">{{ t('showImagesD') }}</div>
            </div>
            <button type="button" role="switch" :aria-checked="state.draft.show_link_images !== false" :aria-label="t('showImages')"
                    class="relative h-6 w-11 shrink-0 rounded-full transition-colors" :class="state.draft.show_link_images !== false ? 'bg-brand' : 'bg-line'"
                    @click="state.draft.show_link_images = state.draft.show_link_images === false">
                <span class="absolute top-0.5 size-5 rounded-full bg-white shadow transition-all" :class="state.draft.show_link_images !== false ? 'end-0.5' : 'start-0.5'"></span>
            </button>
        </div>

        <h3 class="mt-7 text-lg font-bold">{{ t('yourLinks') }}</h3>
        <ul class="mt-3 space-y-3">
            <li v-for="(l, i) in state.draft.links" :key="i" class="rounded-[8px] border border-line p-3">
                <div class="flex items-center gap-2">
                    <input v-model="l.label" class="field" :placeholder="t('linkLabel')" :aria-label="t('linkLabel')" maxlength="60">
                    <button type="button" class="icon-btn shrink-0 text-muted hover:text-red-700" :aria-label="t('delete')" @click="remove(i)"><Icon name="trash" :size="16" /></button>
                </div>
                <input v-model="l.url" dir="ltr" class="field mt-2 text-start" :placeholder="t('linkUrl')" :aria-label="t('linkUrl')" inputmode="url">
                <textarea v-model="l.description" class="field mt-2" rows="2" maxlength="160" :placeholder="t('linkDesc')" :aria-label="t('linkDesc')"></textarea>
                <p v-if="err('links.' + i + '.url') || err('links.' + i + '.label')" class="mt-1 text-sm text-red-600">{{ err('links.' + i + '.url') || err('links.' + i + '.label') }}</p>
            </li>
        </ul>
        <button type="button" class="btn btn-ghost mt-3 w-full" :disabled="state.draft.links.length >= 10" @click="add"><Icon name="plus" :size="16" /> {{ t('addLink') }}</button>

        <h3 class="mt-9 text-lg font-bold">{{ t('socials') }}</h3>
        <p class="hint mt-1">{{ t('socialsHint') }}</p>
        <div class="mt-4 space-y-3">
            <div v-for="n in SOCIAL_NAMES" :key="n" class="flex items-center gap-3">
                <span class="grid size-9 shrink-0 place-items-center text-ink"><SocialIcon :name="n" :size="18" /></span>
                <input v-model="state.draft.socials[n]" dir="ltr" class="field text-start" :placeholder="n" :aria-label="n">
            </div>
            <p v-if="err('socials')" class="text-sm text-red-600">{{ err('socials') }}</p>
        </div>
        <SaveBar :busy="busy" :error="message" />
    </form>
</template>
