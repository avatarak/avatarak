<script setup>
import { computed, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import Icon from './Icon.vue';
import InitialsAvatar from './InitialsAvatar.vue';
import SocialIcon from './SocialIcon.vue';
import { SOCIAL_URLS } from '../icons';
import { t } from '../i18n';

/**
 * The public profile, rendered live. resources/views/profile.blade.php mirrors this markup and these
 * measurements (taken from gravatar.com): 640px cards with 4px radius and 24px gaps, 148px avatar,
 * 34px name, 16px text, 42px buttons, 18px social icons, 192x108 link thumbnails. Keep them in sync.
 */
const props = defineProps({ profile: Object, avatarSrc: String, verified: Boolean, verifiedAccounts: { type: Array, default: () => [] } });

const hidden = (s) => (props.profile.hidden_sections || []).includes(s);
const socials = computed(() => Object.entries(props.profile.socials || {}).filter(([n, v]) => v && SOCIAL_URLS[n]));
const links = computed(() => (props.profile.links || []).filter((l) => l.label && l.url));
const showImages = computed(() => props.profile.show_link_images !== false);
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
watch(() => [props.profile.bio, hidden('bio')], () => nextTick(measure));
onMounted(() => { nextTick(measure); window.addEventListener('resize', measure); });
onBeforeUnmount(() => window.removeEventListener('resize', measure));
</script>

<template>
    <div class="profile-canvas min-h-full px-4 py-12" :style="profile.accent_color ? { '--accent': profile.accent_color } : null">
        <div class="mx-auto max-w-[640px] space-y-6">
            <article class="card relative overflow-hidden !rounded-[4px] px-6 pb-8 pt-8 sm:px-10">
                <div class="absolute inset-x-0 top-0 h-36 overflow-hidden bg-[color-mix(in_srgb,var(--accent)_22%,#fff)]" aria-hidden="true">
                    <img v-if="avatarSrc" :src="avatarSrc" alt="" class="size-full scale-150 object-cover opacity-80 blur-2xl">
                </div>

                <div class="relative w-fit">
                    <img v-if="avatarSrc" :src="avatarSrc" alt="" class="size-[9.25rem] rounded-full bg-line object-cover">
                    <div v-else class="size-[9.25rem] overflow-hidden rounded-full bg-line"><InitialsAvatar :name="display" :seed="profile.username" /></div>
                    <span class="absolute end-0 top-0 grid size-[38px] place-items-center rounded-full bg-ink text-white"><Icon name="qr" :size="20" /></span>
                </div>

                <h1 class="mt-4 text-[2.125rem] font-bold leading-[1.1] tracking-[-0.01em]">{{ display }}</h1>
                <p v-if="profile.name_fa && profile.name_en" class="text-base leading-6 text-ink/75"><bdi dir="ltr">{{ profile.name_en }}</bdi></p>
                <p v-if="profile.headline" class="text-base leading-6 text-ink/75">{{ profile.headline }}</p>
                <p v-if="profile.location" class="text-base leading-6 text-ink/75">{{ profile.location }}</p>

                <div v-if="verified || socials.length" class="mt-7 flex flex-wrap items-center gap-3 text-ink">
                    <svg v-if="verified" width="22" height="22" viewBox="0 0 24 24" aria-label="verified" class="text-[#3B6BF0]">
                        <path fill="currentColor" d="M12 1.5l2.6 1.9 3.2-.1 1 3 2.6 1.9-1 3 1 3-2.6 1.9-1 3-3.2-.1L12 22.5l-2.6-1.9-3.2.1-1-3-2.6-1.9 1-3-1-3 2.6-1.9 1-3 3.2.1z"/>
                        <path fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M8 12.3l2.7 2.7L16 9.6"/>
                    </svg>
                    <span v-if="verified && socials.length" class="h-px w-3.5 bg-ink/35"></span>
                    <span v-for="[n] in socials" :key="n" :title="n"><SocialIcon :name="n" :size="18" /></span>
                </div>

                <div v-if="profile.bio && !hidden('bio')" class="mt-7">
                    <p ref="bioEl" class="whitespace-pre-line text-base leading-[1.45]" :class="expanded ? '' : 'line-clamp-3'">{{ profile.bio }}</p>
                    <button v-if="overflows" type="button" class="mt-1 text-base text-[var(--accent)] hover:underline" @click="expanded = !expanded; nextTick(measure)">
                        {{ expanded ? t('showLess') : t('showMore') }}
                    </button>
                </div>

                <div class="mt-6 grid gap-4" :class="profile.website ? 'grid-cols-2' : ''">
                    <span class="accent-btn">{{ t('share') }}</span>
                    <span v-if="profile.website" class="accent-btn">{{ t('website') }}</span>
                </div>
            </article>

            <section v-if="links.length && !hidden('links')" class="card !rounded-[4px] px-6 pb-8 pt-8 sm:px-10">
                <h2 class="text-lg font-bold leading-[1.5]">{{ t('sec_links') }}</h2>
                <ul class="mt-3 divide-y divide-line">
                    <li v-for="(l, i) in links" :key="i" class="flex items-start gap-4 py-4 sm:gap-6">
                        <img v-if="showImages && l.thumb" :src="`/thumb/${l.thumb}`" alt="" loading="lazy" class="aspect-video w-28 shrink-0 rounded-[4px] bg-line object-cover sm:w-48">
                        <span v-else class="grid aspect-video w-28 shrink-0 place-items-center rounded-[4px] bg-[color-mix(in_srgb,var(--accent)_8%,#fff)] text-[var(--accent)] sm:w-48"
                              :class="showImages && l.thumb === undefined ? 'animate-pulse' : ''"><Icon name="link" :size="22" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1 text-sm font-bold leading-6 text-[var(--accent)]"><span class="truncate">{{ l.label }}</span><Icon name="external" :size="12" class="shrink-0" /></span>
                            <span v-if="l.description" class="line-clamp-3 block text-sm leading-[26px] text-ink/75">{{ l.description }}</span>
                            <span v-else class="block truncate text-sm leading-6 text-ink/60"><bdi dir="ltr">{{ host(l.url) }}</bdi></span>
                        </span>
                    </li>
                </ul>
            </section>

            <section v-if="verifiedAccounts.length && !hidden('verified')" class="card !rounded-[4px] px-6 pb-6 pt-8 sm:px-10">
                <h2 class="text-lg font-bold leading-[1.5]">{{ t('sec_verified') }}</h2>
                <ul class="mt-3 divide-y divide-line">
                    <li v-for="v in verifiedAccounts" :key="v.id" class="py-4">
                        <div class="flex items-center gap-2 text-sm font-bold leading-6">
                            <SocialIcon v-if="v.network !== 'website'" :name="v.network" :size="18" />
                            <Icon v-else name="globe" :size="18" />
                            {{ v.network === 'website' ? t('website') : ({ github: 'GitHub', telegram: 'Telegram', linkedin: 'LinkedIn' }[v.network] || v.network.charAt(0).toUpperCase() + v.network.slice(1)) }}
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-label="verified" class="text-[#3B6BF0]">
                                <path fill="currentColor" d="M12 1.5l2.6 1.9 3.2-.1 1 3 2.6 1.9-1 3 1 3-2.6 1.9-1 3-3.2-.1L12 22.5l-2.6-1.9-3.2.1-1-3-2.6-1.9 1-3-1-3 2.6-1.9 1-3 3.2.1z"/>
                                <path fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M8 12.3l2.7 2.7L16 9.6"/>
                            </svg>
                        </div>
                        <div class="mt-0.5 truncate text-base text-[var(--accent)]"><bdi dir="ltr">{{ v.url ? v.url.replace(/^https?:\/\//, '') : (v.label || v.identifier) }}</bdi></div>
                    </li>
                </ul>
            </section>

            <div class="flex justify-center pt-2">
                <span class="inline-flex min-h-[42px] items-center gap-2 rounded-full bg-white/60 px-4 py-2 text-sm text-ink"><Icon name="code" :size="18" /> {{ t('advanced') }}</span>
            </div>
        </div>
    </div>
</template>
