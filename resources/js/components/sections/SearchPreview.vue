<script setup>
import { computed } from 'vue';
import { state } from '../../store';
import { t } from '../../i18n';
import { previewLang, localize } from '../../lang';
import Icon from '../Icon.vue';

const brand = window.__BRAND__ || {};
const d = computed(() => state.draft);
const lang = computed(() => previewLang(d.value));
const p = computed(() => localize(d.value, lang.value));      // the texts the page shows in that language
const siteName = computed(() => (lang.value === 'en' ? brand.name_en || brand.name : brand.name) || '');
const name = computed(() => (lang.value === 'en' ? p.value.name_en || p.value.name_fa : p.value.name_fa || p.value.name_en) || p.value.username);
const title = computed(() => `${name.value} — ${siteName.value}`);
const clean = (s) => (s || '').replace(/\s+/g, ' ').trim();
const cut = (s, n) => (s.length > n ? s.slice(0, n - 1).trimEnd() + '…' : s);
const description = computed(() => {
    const bio = clean(p.value.bio);
    if (bio) return cut(bio, 160);
    const line = [clean(p.value.headline), clean(p.value.location)].filter(Boolean).join(' · ');
    if (line) return cut(`${name.value} — ${line}`, 160);
    return lang.value === 'en' ? `${name.value}'s public profile on ${siteName.value}.` : `پروفایل عمومی ${name.value} در ${siteName.value}.`;
});
const url = computed(() => `${location.host} › ${d.value.username}`);

// what the sitemap needs (mirrors App\Http\Controllers\SeoFilesController::listable)
const hasName = computed(() => !!(d.value.name_fa || d.value.name_en));
const hasText = computed(() => !!(d.value.bio || d.value.headline || d.value.bio_alt || d.value.headline_alt));
const state_ = computed(() => (d.value.show_profile === false ? 'private' : !d.value.allow_indexing ? 'off' : !hasName.value || !hasText.value ? 'thin' : 'ok'));
</script>

<template>
    <section class="mt-8">
        <h3 class="text-lg font-bold">{{ t('seoTitle') }}</h3>

        <p class="mt-2 flex items-start gap-2 rounded-[8px] p-3 text-sm leading-6"
           :class="state_ === 'ok' ? 'bg-emerald-50 text-emerald-900' : state_ === 'thin' ? 'bg-amber-50 text-amber-900' : 'bg-soft text-ink-2'" role="status">
            <Icon :name="state_ === 'ok' ? 'check' : 'alert'" :size="18" class="mt-0.5 shrink-0" />
            <span>{{ t('seo_' + state_) }}</span>
        </p>

        <ul v-if="state_ === 'thin'" class="mt-2 space-y-1 text-sm">
            <li :class="hasName ? 'text-emerald-700' : 'text-amber-800'">{{ hasName ? '✓' : '✗' }} {{ t('seoNeedName') }}</li>
            <li :class="hasText ? 'text-emerald-700' : 'text-amber-800'">{{ hasText ? '✓' : '✗' }} {{ t('seoNeedText') }}</li>
        </ul>

        <div v-if="state_ === 'ok' || state_ === 'thin'" class="mt-4">
            <div class="hint mb-1.5">{{ t('seoPreview') }}</div>
            <div class="rounded-[8px] border border-line bg-white p-4" :dir="lang === 'en' ? 'ltr' : 'rtl'">
                <div class="truncate text-[13px] text-[#4d5156]" dir="ltr"><bdi>{{ url }}</bdi></div>
                <div class="mt-0.5 truncate text-[19px] leading-snug text-[#1a0dab]">{{ title }}</div>
                <div class="mt-1 text-sm leading-[1.55] text-[#4d5156]">{{ description }}</div>
            </div>
            <p class="hint mt-2">{{ t('seoNote') }}</p>
        </div>
    </section>
</template>
