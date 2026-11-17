<script setup>
import { computed } from 'vue';
import { t } from '../i18n';

const props = defineProps({ profile: Object, avatarSrc: String });
const order = computed(() => {
    const base = (props.profile.section_order || []).filter((s) => ['bio', 'links', 'website'].includes(s));
    return [...new Set([...base, 'bio', 'links', 'website'])];
});
const links = computed(() => Object.entries(props.profile.socials || {}).filter(([, v]) => v));
const display = computed(() => props.profile.name_fa || props.profile.name_en || props.profile.username || t('yourName'));
</script>

<template>
    <article class="card overflow-hidden" :style="profile.accent_color ? { '--brand': profile.accent_color } : null">
        <div class="h-20" style="background: color-mix(in srgb, var(--brand) 16%, var(--surface))"></div>
        <div class="px-5 pb-6 text-center">
            <div class="-mt-12 mb-3 flex justify-center">
                <img v-if="avatarSrc" :src="avatarSrc" alt="" class="size-24 rounded-full border-4 border-surface bg-line object-cover">
                <div v-else class="size-24 rounded-full border-4 border-surface bg-line"></div>
            </div>
            <div class="font-display text-xl font-bold">{{ display }}</div>
            <div v-if="profile.name_fa && profile.name_en" dir="ltr" class="text-sm text-muted">{{ profile.name_en }}</div>
            <div dir="ltr" class="text-sm text-muted">@{{ profile.username }}</div>

            <template v-for="s in order" :key="s">
                <p v-if="s === 'bio'" class="mt-4 text-sm leading-7">{{ profile.bio || t('yourBio') }}</p>
                <p v-else-if="s === 'website' && profile.website" class="mt-4 text-sm font-medium text-brand">{{ profile.website.replace(/^https?:\/\//, '') }}</p>
                <ul v-else-if="s === 'links' && links.length" class="mt-4 flex flex-wrap justify-center gap-2">
                    <li v-for="[n] in links" :key="n" class="rounded-lg border border-line px-2.5 py-1 text-xs">{{ n }}</li>
                </ul>
            </template>
        </div>
    </article>
</template>
