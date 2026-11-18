<script setup>
import { computed, ref } from 'vue';
import { state, toast } from '../../store';
import { t } from '../../i18n';
import { copyText } from '../../composables';
import Icon from '../Icon.vue';

const u = computed(() => state.account.profile.username);
const base = computed(() => `/${u.value}`);
const publicUrl = computed(() => `${window.location.origin}/${u.value}`);
const hidden = computed(() => state.draft.show_profile === false);
const busy = ref(false);

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
async function copy() { await copyText(publicUrl.value); toast(t('linkCopied')); }
</script>

<template>
    <div class="space-y-8">
        <p v-if="hidden" class="rounded-[8px] border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-900" role="status">{{ t('shareHidden') }}</p>
        <template v-else>
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
                <h3 class="text-lg font-bold">{{ t('shareCardTitle') }}</h3>
                <p class="hint mt-1">{{ t('shareCardD') }}</p>
                <a :href="`${base}/print`" target="_blank" rel="noopener" class="btn btn-primary mt-3 w-full"><Icon name="printer" :size="18" /> {{ t('shareCardOpen') }}</a>
            </section>

            <section>
                <h3 class="text-lg font-bold">{{ t('shareImgTitle') }}</h3>
                <p class="hint mt-1">{{ t('shareImgD') }}</p>
                <img :src="`${base}/share.svg`" alt="" class="mx-auto mt-3 w-48 rounded-[8px] border border-line" loading="lazy">
                <div class="mt-3 flex flex-wrap justify-center gap-2">
                    <button type="button" class="btn btn-primary" :disabled="busy" @click="downloadPng(`${base}/share.svg`, 1080, 1350, `${u}-card.png`)">
                        <Icon name="download" :size="16" /> {{ busy ? t('shareDownloading') : t('shareImgPng') }}
                    </button>
                    <a :href="`${base}/share.svg?download=1`" class="btn btn-ghost"><Icon name="download" :size="16" /> {{ t('shareImgSvg') }}</a>
                </div>
            </section>

            <a :href="`${base}.vcf`" class="btn btn-ghost w-full"><Icon name="idCard" :size="18" /> {{ t('shareVcard') }}</a>
        </template>
    </div>
</template>
