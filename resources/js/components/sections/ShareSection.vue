<script setup>
import { computed, ref } from 'vue';
import { state, toast } from '../../store';
import { useSubmit } from '../../composables';
import { t } from '../../i18n';
import { copyText } from '../../composables';
import Icon from '../Icon.vue';
import LangTabs from '../LangTabs.vue';
import { editLang } from '../../lang';

const u = computed(() => state.account.profile.username);
const base = computed(() => `/${u.value}`);
const publicUrl = computed(() => `${window.location.origin}/${u.value}`);
const hidden = computed(() => state.draft.show_profile === false);
const busy = ref(false);
// a bilingual profile: the card, image and contact file follow the language tab at the top
const lg = computed(() => (state.draft.languages === 'both' ? `lang=${editLang()}` : ''));
const withLang = (url) => (lg.value ? `${url}${url.includes('?') ? '&' : '?'}${lg.value}` : url);

/** Rasterise one of our own SVGs (same origin, so the canvas stays clean) and download it as PNG. */
async function downloadPng(url, w, h, filename) {
    busy.value = true;
    try {
        const svg = await (await fetch(url, { credentials: 'same-origin' })).text();
        const img = new Image();
        const blobUrl = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));
        await new Promise((res, rej) => { img.onload = res; img.onerror = rej; img.src = blobUrl; });
        const c = document.createElement('canvas');
        c.width = w; c.height = h;
        const ctx = c.getContext('2d');
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, w, h);
        ctx.drawImage(img, 0, 0, w, h);
        URL.revokeObjectURL(blobUrl);
        const png = await new Promise((r) => c.toBlob(r, 'image/png'));
        const a = document.createElement('a');
        a.href = URL.createObjectURL(png); a.download = filename; a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 4000);
    } finally { busy.value = false; }
}
const STYLES = ['classic', 'band', 'bold'];
const style = computed(() => state.draft.card_style || 'classic');
const { busy: cardBusy, run: saveCard, err: cardErr, message: cardMsg } = useSubmit('PATCH', '/account/card', () => ({
    card_phone: state.draft.card_phone || null, card_phone_public: !!state.draft.card_phone_public, card_style: style.value,
}));
async function copy() { await copyText(publicUrl.value); toast(t('linkCopied')); }
</script>

<template>
    <div class="space-y-8">
        <p v-if="hidden" class="rounded-[8px] border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-900" role="status">{{ t('shareHidden') }}</p>
        <template v-else>
            <LangTabs />
            <section>
                <h3 class="text-lg font-bold">{{ t('shareQr') }}</h3>
                <div class="mt-3 flex flex-col items-center gap-4 rounded-[8px] border border-line p-5">
                    <img :src="`${base}.qr?size=320&v=${state.account.profile.accent_color || ''}`" :alt="t('shareQr')" width="200" height="200" class="size-[200px]">
                    <p dir="ltr" class="max-w-full truncate text-sm text-muted">{{ publicUrl.replace(/^https?:\/\//, '') }}</p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <button type="button" class="btn btn-ghost" @click="copy"><Icon name="copy" :size="16" /> {{ t('shareLink') }}</button>
                        <a :href="`${base}.qr?size=1024&download=1`" class="btn btn-ghost"><Icon name="download" :size="16" /> {{ t('shareQrSvg') }}</a>
                        <button type="button" class="btn btn-ghost" :disabled="busy" @click="downloadPng(`${base}.qr?size=1024`, 1024, 1024, `${u}-qr.png`)"><Icon name="download" :size="16" /> {{ t('shareQrPng') }}</button>
                    </div>
                </div>
            </section>

            <section>
                <h3 class="text-lg font-bold">{{ t('cardOptTitle') }}</h3>
                <form class="mt-3 space-y-4 rounded-[8px] border border-line p-4" @submit.prevent="saveCard">
                    <div>
                        <span class="text-sm font-medium">{{ t('cardStyle') }}</span>
                        <div class="mt-2 grid grid-cols-3 gap-2" role="radiogroup" :aria-label="t('cardStyle')">
                            <button v-for="s in STYLES" :key="s" type="button" role="radio" :aria-checked="style === s"
                                    class="rounded-[6px] border bg-white p-2 text-center text-sm font-medium transition-colors"
                                    :class="style === s ? 'border-ink ring-1 ring-ink' : 'border-line hover:border-muted'" @click="state.draft.card_style = s">
                                <img :src="withLang(`${base}/share.svg?style=${s}&v=${state.account.profile.accent_color || ''}`)" alt="" class="mx-auto mb-1 w-full max-w-[96px] rounded-[4px] border border-line" loading="lazy">
                                {{ t('cardStyle_' + s) }}
                            </button>
                        </div>
                    </div>
                    <div>
                        <label for="card-phone" class="text-sm font-medium">{{ t('cardPhone') }}</label>
                        <input id="card-phone" v-model="state.draft.card_phone" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" maxlength="40" placeholder="09123456789" class="field mt-1 text-start">
                        <p class="hint mt-1">{{ t('cardPhoneHint') }}</p>
                        <p v-if="cardErr('card_phone')" class="mt-1 text-sm text-red-600" role="alert">{{ cardErr('card_phone') }}</p>
                    </div>
                    <div v-if="state.draft.card_phone">
                        <label class="flex items-start gap-3 text-sm"><input v-model="state.draft.card_phone_public" type="checkbox" class="mt-1 size-5"><span>{{ t('cardPhonePublic') }}</span></label>
                        <p v-if="state.draft.card_phone_public" class="mt-2 rounded-[6px] bg-amber-50 p-3 text-sm text-amber-900">{{ t('cardPhonePublicHint') }}</p>
                    </div>
                    <button class="btn btn-primary w-full" :disabled="cardBusy">{{ cardBusy ? t('saving') : t('cardOptSave') }}</button>
                    <p v-if="cardMsg" class="text-sm text-red-600" role="alert">{{ cardMsg }}</p>
                </form>
            </section>

            <section>
                <h3 class="text-lg font-bold">{{ t('shareCardTitle') }}</h3>
                <p class="hint mt-1">{{ t('cardPrintD') }}</p>
                <a :href="withLang(`${base}/print?style=${style}`)" target="_blank" rel="noopener" class="btn btn-primary mt-3 w-full"><Icon name="printer" :size="18" /> {{ t('shareCardOpen') }}</a>
            </section>

            <section>
                <h3 class="text-lg font-bold">{{ t('shareImgTitle') }}</h3>
                <p class="hint mt-1">{{ t('shareImgD') }}</p>
                <img :src="withLang(`${base}/share.svg?style=${style}`)" alt="" class="mx-auto mt-3 w-48 rounded-[8px] border border-line" loading="lazy">
                <div class="mt-3 flex flex-wrap justify-center gap-2">
                    <button type="button" class="btn btn-primary" :disabled="busy" @click="downloadPng(withLang(`${base}/share.svg?style=${style}`), 1080, 1350, `${u}-card.png`)">
                        <Icon name="download" :size="16" /> {{ busy ? t('shareDownloading') : t('shareImgPng') }}
                    </button>
                    <a :href="withLang(`${base}/share.svg?style=${style}&download=1`)" class="btn btn-ghost"><Icon name="download" :size="16" /> {{ t('shareImgSvg') }}</a>
                </div>
            </section>

            <a :href="withLang(`${base}.vcf`)" class="btn btn-ghost w-full"><Icon name="idCard" :size="18" /> {{ t('shareVcard') }}</a>
        </template>
    </div>
</template>
