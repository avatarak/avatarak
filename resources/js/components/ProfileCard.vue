<script setup>
import { computed } from 'vue';
import Icon from './Icon.vue';
import SocialIcon from './SocialIcon.vue';
import { SOCIAL_URLS } from '../icons';
import { t } from '../i18n';

/**
 * The public profile, rendered live. resources/views/profile.blade.php mirrors this markup and
 * these classes, so the preview and the real page look the same. Keep them in sync.
 */
const props = defineProps({ profile: Object, avatarSrc: String, verified: Boolean });

const SECTIONS = ['bio', 'links'];
const order = computed(() => [...new Set([...(props.profile.section_order || []).filter((s) => SECTIONS.includes(s)), ...SECTIONS])]
    .filter((s) => !(props.profile.hidden_sections || []).includes(s)));
const socials = computed(() => Object.entries(props.profile.socials || {}).filter(([n, v]) => v && SOCIAL_URLS[n]));
const links = computed(() => (props.profile.links || []).filter((l) => l.label && l.url));
const display = computed(() => props.profile.name_fa || props.profile.name_en || props.profile.username || t('yourName'));
const host = (u) => { try { return new URL(u).host; } catch { return u; } };
</script>

<template>
    <div class="profile-canvas min-h-full px-4 py-10 sm:py-14" :style="profile.accent_color ? { '--accent': profile.accent_color } : null">
        <div class="mx-auto max-w-[640px] space-y-5">
            <article class="card overflow-hidden">
                <div class="relative h-44 overflow-hidden bg-[color-mix(in_srgb,var(--accent)_22%,#fff)]">
                    <img v-if="avatarSrc" :src="avatarSrc" alt="" class="absolute inset-0 size-full scale-150 object-cover opacity-80 blur-2xl">
                </div>
                <div class="px-6 pb-8 sm:px-9">
                    <div class="relative -mt-[5.5rem]">
                        <img v-if="avatarSrc" :src="avatarSrc" alt="" class="size-40 rounded-full bg-line object-cover shadow-[0_2px_14px_rgb(0_0_0/.16)] sm:size-44">
                        <div v-else class="size-40 rounded-full bg-line sm:size-44"></div>
                    </div>

                    <h1 class="mt-5 text-[2.5rem] font-bold leading-tight tracking-tight">{{ display }}</h1>
                    <p v-if="profile.name_fa && profile.name_en" class="mt-0.5 text-lg text-muted"><bdi dir="ltr">{{ profile.name_en }}</bdi></p>
                    <p v-if="profile.headline" class="mt-2 text-xl leading-snug text-muted">{{ profile.headline }}</p>
                    <p v-if="profile.location" class="mt-2 flex items-center gap-1.5 text-muted"><Icon name="pin" :size="18" /> {{ profile.location }}</p>

                    <div v-if="verified || socials.length" class="mt-6 flex flex-wrap items-center gap-4 text-ink">
                        <svg v-if="verified" width="28" height="28" viewBox="0 0 24 24" aria-label="verified" class="text-[#3B6BF0]">
                            <path fill="currentColor" d="M12 1.5l2.6 1.9 3.2-.1 1 3 2.6 1.9-1 3 1 3-2.6 1.9-1 3-3.2-.1L12 22.5l-2.6-1.9-3.2.1-1-3-2.6-1.9 1-3-1-3 2.6-1.9 1-3 3.2.1z"/>
                            <path fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M8 12.3l2.7 2.7L16 9.6"/>
                        </svg>
                        <span v-if="verified && socials.length" class="h-px w-3.5 bg-muted/50"></span>
                        <span v-for="[n] in socials" :key="n" :title="n"><SocialIcon :name="n" :size="26" /></span>
                    </div>

                    <div class="mt-8 grid gap-3" :class="profile.website ? 'sm:grid-cols-2' : ''">
                        <span class="accent-btn"><Icon name="share" :size="19" /> {{ t('share') }}</span>
                        <span v-if="profile.website" class="accent-btn"><Icon name="external" :size="19" /> {{ t('visitSite') }}</span>
                    </div>
                </div>
            </article>

            <template v-for="s in order" :key="s">
                <section v-if="s === 'bio' && profile.bio" class="card p-6 sm:p-9">
                    <h2 class="text-2xl font-bold">{{ t('sec_bio') }}</h2>
                    <p class="mt-4 whitespace-pre-line text-[1.0625rem] leading-8">{{ profile.bio }}</p>
                </section>
                <section v-else-if="s === 'links' && links.length" class="card p-6 sm:p-9">
                    <h2 class="text-2xl font-bold">{{ t('sec_links') }}</h2>
                    <ul class="mt-4 -mx-3 divide-y divide-line">
                        <li v-for="(l, i) in links" :key="i" class="flex items-center gap-4 px-3 py-4">
                            <span class="grid size-12 shrink-0 place-items-center rounded-[8px] bg-[color-mix(in_srgb,var(--accent)_10%,#fff)] text-[var(--accent)]"><Icon name="link" :size="22" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-1.5 font-semibold text-[var(--accent)]"><span class="truncate">{{ l.label }}</span><Icon name="external" :size="15" class="shrink-0" /></span>
                                <span class="block truncate text-sm text-muted"><bdi dir="ltr">{{ host(l.url) }}</bdi></span>
                            </span>
                        </li>
                    </ul>
                </section>
            </template>
        </div>
    </div>
</template>
