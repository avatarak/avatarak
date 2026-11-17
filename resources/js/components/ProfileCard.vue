<script setup>
import { computed, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import Icon from './Icon.vue';
import SocialIcon from './SocialIcon.vue';
import { SOCIAL_URLS } from '../icons';
import { t } from '../i18n';

/**
 * The public profile, rendered live. resources/views/profile.blade.php mirrors this markup and
 * these classes, so the preview and the real page look the same. Keep them in sync.
 */
const props = defineProps({ profile: Object, avatarSrc: String, verified: Boolean });

const hidden = (s) => (props.profile.hidden_sections || []).includes(s);
const socials = computed(() => Object.entries(props.profile.socials || {}).filter(([n, v]) => v && SOCIAL_URLS[n]));
const links = computed(() => (props.profile.links || []).filter((l) => l.label && l.url));
const display = computed(() => props.profile.name_fa || props.profile.name_en || props.profile.username || t('yourName'));
const host = (u) => { try { return new URL(u).host; } catch { return u; } };

// Bio: 3 lines + "Show more" only when it really overflows.
const bioEl = ref(null);
const expanded = ref(false);
const overflows = ref(false);
function measure() {
    const el = bioEl.value;
    if (!el) return;
    const was = expanded.value;
    el.classList.add('line-clamp-3');
    overflows.value = el.scrollHeight > el.clientHeight + 1;
    if (was) el.classList.remove('line-clamp-3');
}
watch(() => [props.profile.bio, hidden('bio')], () => nextTick(measure), { immediate: false });
onMounted(() => { nextTick(measure); window.addEventListener('resize', measure); });
onBeforeUnmount(() => window.removeEventListener('resize', measure));
</script>

<template>
    <div class="profile-canvas min-h-full px-4 py-10 sm:py-14" :style="profile.accent_color ? { '--accent': profile.accent_color } : null">
        <div class="mx-auto max-w-[640px] space-y-5">
            <article class="card overflow-hidden">
                <div class="relative h-36 overflow-hidden bg-[color-mix(in_srgb,var(--accent)_22%,#fff)]">
                    <img v-if="avatarSrc" :src="avatarSrc" alt="" class="absolute inset-0 size-full scale-150 object-cover opacity-80 blur-2xl">
                </div>
                <div class="px-6 pb-9 sm:px-12">
                    <div class="relative -mt-[6.5rem] w-fit">
                        <img v-if="avatarSrc" :src="avatarSrc" alt="" class="size-36 rounded-full bg-line object-cover shadow-[0_2px_14px_rgb(0_0_0/.16)] sm:size-40">
                        <div v-else class="size-36 rounded-full bg-line sm:size-40"></div>
                    </div>

                    <h1 class="mt-6 text-[2.5rem] font-bold leading-[1.15] tracking-tight">{{ display }}</h1>
                    <p v-if="profile.name_fa && profile.name_en" class="mt-1 text-lg text-ink-2"><bdi dir="ltr">{{ profile.name_en }}</bdi></p>
                    <p v-if="profile.headline" class="mt-2 text-xl leading-snug text-ink-2">{{ profile.headline }}</p>
                    <p v-if="profile.location" class="mt-1 flex items-center gap-1.5 text-lg text-ink-2"><Icon name="pin" :size="18" class="shrink-0 opacity-70" /> {{ profile.location }}</p>

                    <div v-if="verified || socials.length" class="mt-10 flex flex-wrap items-center gap-3.5 text-ink">
                        <svg v-if="verified" width="24" height="24" viewBox="0 0 24 24" aria-label="verified" class="text-[#3B6BF0]">
                            <path fill="currentColor" d="M12 1.5l2.6 1.9 3.2-.1 1 3 2.6 1.9-1 3 1 3-2.6 1.9-1 3-3.2-.1L12 22.5l-2.6-1.9-3.2.1-1-3-2.6-1.9 1-3-1-3 2.6-1.9 1-3 3.2.1z"/>
                            <path fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M8 12.3l2.7 2.7L16 9.6"/>
                        </svg>
                        <span v-if="verified && socials.length" class="h-px w-3 bg-muted/50"></span>
                        <span v-for="[n] in socials" :key="n" :title="n"><SocialIcon :name="n" :size="22" /></span>
                    </div>

                    <div v-if="profile.bio && !hidden('bio')" class="mt-10">
                        <p ref="bioEl" class="whitespace-pre-line text-[1.0625rem] leading-7" :class="expanded ? '' : 'line-clamp-3'">{{ profile.bio }}</p>
                        <button v-if="overflows" type="button" class="mt-1 font-medium text-[var(--accent)] hover:underline" @click="expanded = !expanded; nextTick(measure)">
                            {{ expanded ? t('showLess') : t('showMore') }}
                        </button>
                    </div>

                    <div class="mt-8 grid gap-3" :class="profile.website ? 'grid-cols-2' : ''">
                        <span class="accent-btn">{{ t('share') }}</span>
                        <span v-if="profile.website" class="accent-btn">{{ t('visitSite') }}</span>
                    </div>
                </div>
            </article>

            <section v-if="links.length && !hidden('links')" class="card px-6 py-8 sm:px-12">
                <h2 class="text-[1.375rem] font-bold">{{ t('sec_links') }}</h2>
                <ul class="mt-3 divide-y divide-line">
                    <li v-for="(l, i) in links" :key="i" class="flex items-start gap-4 py-5">
                        <span class="mt-0.5 grid size-14 shrink-0 place-items-center rounded-[8px] bg-[color-mix(in_srgb,var(--accent)_10%,#fff)] text-[var(--accent)]"><Icon name="link" :size="24" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1.5 font-semibold text-[var(--accent)]"><span class="truncate">{{ l.label }}</span><Icon name="external" :size="15" class="shrink-0" /></span>
                            <span v-if="l.description" class="mt-1 line-clamp-3 block text-[0.9375rem] leading-6 text-ink-2">{{ l.description }}</span>
                            <span v-else class="mt-0.5 block truncate text-sm text-muted"><bdi dir="ltr">{{ host(l.url) }}</bdi></span>
                        </span>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
