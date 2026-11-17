<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import Icon from './Icon.vue';
import { t } from '../i18n';

const props = defineProps({ file: File });
const emit = defineEmits(['done', 'cancel', 'error']);

const STAGE = 300;   // px, the square viewport (shown through a circular mask)
const OUT = 800;     // px, exported square size
const img = ref(null);
const natural = ref({ w: 0, h: 0 });
const url = URL.createObjectURL(props.file);
const zoom = ref(1);
const rot = ref(0);          // degrees: 0 / 90 / 180 / 270
const off = ref({ x: 0, y: 0 });
const busy = ref(false);
const root = ref(null);

// dimensions of the image after rotation
const dims = computed(() => (rot.value % 180 === 0 ? natural.value : { w: natural.value.h, h: natural.value.w }));
const cover = computed(() => (dims.value.w ? STAGE / Math.min(dims.value.w, dims.value.h) : 1));
const scale = computed(() => cover.value * zoom.value);

function clamp() {
    const mx = Math.max(0, (dims.value.w * scale.value - STAGE) / 2);
    const my = Math.max(0, (dims.value.h * scale.value - STAGE) / 2);
    off.value = { x: Math.min(mx, Math.max(-mx, off.value.x)), y: Math.min(my, Math.max(-my, off.value.y)) };
}
function setZoom(z) { zoom.value = Math.min(4, Math.max(1, z)); clamp(); }
function rotate() { rot.value = (rot.value + 90) % 360; off.value = { x: 0, y: 0 }; clamp(); }
function reset() { zoom.value = 1; rot.value = 0; off.value = { x: 0, y: 0 }; }

let drag = null;
function down(e) { drag = { x: e.clientX, y: e.clientY, ox: off.value.x, oy: off.value.y }; e.currentTarget.setPointerCapture(e.pointerId); }
function move(e) {
    if (!drag) return;
    off.value = { x: drag.ox + e.clientX - drag.x, y: drag.oy + e.clientY - drag.y };
    clamp();
}
function up() { drag = null; }
function wheel(e) { setZoom(zoom.value + (e.deltaY < 0 ? 0.1 : -0.1)); }
function key(e) {
    const step = 10;
    const map = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
    if (map[e.key]) { e.preventDefault(); off.value = { x: off.value.x + map[e.key][0], y: off.value.y + map[e.key][1] }; clamp(); }
    else if (e.key === '+' || e.key === '=') setZoom(zoom.value + 0.1);
    else if (e.key === '-') setZoom(zoom.value - 0.1);
}

function onLoad() {
    natural.value = { w: img.value.naturalWidth, h: img.value.naturalHeight };
    if (Math.min(natural.value.w, natural.value.h) < 64) emit('error', t('errDim'));
}

function confirm() {
    busy.value = true;
    const k = OUT / STAGE;
    const c = document.createElement('canvas');
    c.width = c.height = OUT;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, OUT, OUT);
    ctx.translate(OUT / 2 + off.value.x * k, OUT / 2 + off.value.y * k);
    ctx.rotate((rot.value * Math.PI) / 180);
    ctx.scale(scale.value * k, scale.value * k);
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(img.value, -natural.value.w / 2, -natural.value.h / 2);
    c.toBlob((b) => { busy.value = false; b ? emit('done', b) : emit('error', t('errLoad')); }, 'image/jpeg', 0.92);
}

function esc(e) { if (e.key === 'Escape') emit('cancel'); }
onMounted(() => { document.addEventListener('keydown', esc); root.value?.querySelector('button')?.focus(); });
onBeforeUnmount(() => { document.removeEventListener('keydown', esc); URL.revokeObjectURL(url); });
</script>

<template>
    <div ref="root" class="fixed inset-0 z-50 grid place-items-center bg-ink/45 p-4" role="dialog" aria-modal="true" :aria-label="t('cropTitle')"
         @click.self="emit('cancel')">
        <div class="card w-full max-w-md p-6">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold">{{ t('cropTitle') }}</h2>
                <button type="button" class="icon-btn" :aria-label="t('close')" @click="emit('cancel')"><Icon name="close" /></button>
            </div>

            <div class="mx-auto mt-5 select-none touch-none overflow-hidden rounded-[6px] bg-ink/90 outline-none"
                 :style="{ width: STAGE + 'px', height: STAGE + 'px', position: 'relative', cursor: 'grab' }"
                 tabindex="0" role="application" :aria-label="t('cropHint')"
                 @pointerdown="down" @pointermove="move" @pointerup="up" @pointercancel="up" @wheel.prevent="wheel" @keydown="key">
                <img ref="img" :src="url" alt="" draggable="false" @load="onLoad" @error="emit('error', t('errLoad'))"
                     class="pointer-events-none absolute max-w-none"
                     :style="{ left: '50%', top: '50%', width: natural.w + 'px', height: natural.h + 'px',
                               transform: `translate(-50%,-50%) translate(${off.x}px,${off.y}px) rotate(${rot}deg) scale(${scale})` }">
                <!-- circular mask: everything outside the circle is dimmed -->
                <div class="pointer-events-none absolute inset-0 rounded-full"
                     style="box-shadow: 0 0 0 999px rgb(16 21 23 / .55), inset 0 0 0 2px rgb(255 255 255 / .9)"></div>
            </div>
            <p class="hint mt-3 text-center">{{ t('cropHint') }}</p>

            <div class="mt-4 flex items-center gap-3">
                <span class="text-sm text-muted">{{ t('zoom') }}</span>
                <input type="range" min="1" max="4" step="0.01" :value="zoom" class="h-2 flex-1 accent-[var(--brand)]"
                       :aria-label="t('zoom')" @input="setZoom(+$event.target.value)">
                <button type="button" class="icon-btn" :title="t('rotate')" :aria-label="t('rotate')" @click="rotate"><Icon name="rotate" :size="20" /></button>
            </div>

            <div class="mt-6 flex items-center justify-between gap-3">
                <button type="button" class="btn btn-quiet" @click="reset">{{ t('reset') }}</button>
                <div class="flex gap-2">
                    <button type="button" class="btn btn-ghost" @click="emit('cancel')">{{ t('cancel') }}</button>
                    <button type="button" class="btn btn-primary" :disabled="busy || !natural.w" @click="confirm">{{ t('setAsAvatar') }}</button>
                </div>
            </div>
        </div>
    </div>
</template>
