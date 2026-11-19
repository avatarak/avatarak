<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import Icon from './Icon.vue';
import Modal from './Modal.vue';
import { t } from '../i18n';

const props = defineProps({ file: File, open: { type: Boolean, default: true } });
const emit = defineEmits(['done', 'cancel', 'error']);

// px, the square viewport (shown through a circular mask): 300, or less on a narrow phone so it never pokes out of the dialog
// (the dialog has 16px of space on each side of the screen and 24px of padding inside)
const fit = () => Math.max(200, Math.min(300, window.innerWidth - 32 - 48));
const stage = ref(fit());
const coarse = window.matchMedia?.('(pointer: coarse)').matches;      // a touch screen: tell people about pinching
const onResize = () => { stage.value = fit(); clamp(); };
onMounted(() => window.addEventListener('resize', onResize));
const OUT = 800;     // px, exported square size
const img = ref(null);
const natural = ref({ w: 0, h: 0 });
const url = URL.createObjectURL(props.file);
const zoom = ref(1);
const rot = ref(0);          // degrees: 0 / 90 / 180 / 270
const off = ref({ x: 0, y: 0 });
const busy = ref(false);

// dimensions of the image after rotation
const dims = computed(() => (rot.value % 180 === 0 ? natural.value : { w: natural.value.h, h: natural.value.w }));
const cover = computed(() => (dims.value.w ? stage.value / Math.min(dims.value.w, dims.value.h) : 1));
const scale = computed(() => cover.value * zoom.value);

function clamp() {
    const mx = Math.max(0, (dims.value.w * scale.value - stage.value) / 2);
    const my = Math.max(0, (dims.value.h * scale.value - stage.value) / 2);
    off.value = { x: Math.min(mx, Math.max(-mx, off.value.x)), y: Math.min(my, Math.max(-my, off.value.y)) };
}
function setZoom(z) { zoom.value = Math.min(4, Math.max(1, z)); clamp(); }
function rotate() { rot.value = (rot.value + 90) % 360; off.value = { x: 0, y: 0 }; clamp(); }
function reset() { zoom.value = 1; rot.value = 0; off.value = { x: 0, y: 0 }; }

// one finger drags the photo; two fingers pinch to zoom (what people expect on a phone)
const pointers = new Map();
let drag = null;
let pinch = null;
function down(e) {
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    e.currentTarget.setPointerCapture(e.pointerId);
    if (pointers.size === 2) {
        const [a, b] = [...pointers.values()];
        pinch = { d: Math.hypot(a.x - b.x, a.y - b.y) || 1, z: zoom.value };
        drag = null;
    } else {
        drag = { x: e.clientX, y: e.clientY, ox: off.value.x, oy: off.value.y };
    }
}
function move(e) {
    if (!pointers.has(e.pointerId)) return;
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (pinch && pointers.size === 2) {
        const [a, b] = [...pointers.values()];
        setZoom(pinch.z * (Math.hypot(a.x - b.x, a.y - b.y) / pinch.d));
        return;
    }
    if (!drag) return;
    off.value = { x: drag.ox + e.clientX - drag.x, y: drag.oy + e.clientY - drag.y };
    clamp();
}
function up(e) {
    pointers.delete(e.pointerId);
    pinch = null;
    drag = pointers.size === 1 ? { x: [...pointers.values()][0].x, y: [...pointers.values()][0].y, ox: off.value.x, oy: off.value.y } : null;
}
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
    const k = OUT / stage.value;
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

onBeforeUnmount(() => { URL.revokeObjectURL(url); window.removeEventListener('resize', onResize); });
</script>

<template>
    <Modal :open="open" :label="t('cropTitle')" width="28rem" @close="emit('cancel')">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold">{{ t('cropTitle') }}</h2>
                <button type="button" class="icon-btn -me-2" :aria-label="t('close')" @click="emit('cancel')"><Icon name="close" :size="20" /></button>
            </div>

            <div class="mx-auto mt-5 select-none touch-none overflow-hidden rounded-[6px] bg-ink/90 outline-none"
                 :style="{ width: stage + 'px', height: stage + 'px', position: 'relative', cursor: 'grab' }"
                 tabindex="0" role="application" :aria-label="t('cropHint')" data-autofocus
                 @pointerdown="down" @pointermove="move" @pointerup="up" @pointercancel="up" @wheel.prevent="wheel" @keydown="key">
                <img ref="img" :src="url" alt="" draggable="false" @load="onLoad" @error="emit('error', t('errLoad'))"
                     class="pointer-events-none absolute max-w-none"
                     :style="{ left: '50%', top: '50%', width: natural.w + 'px', height: natural.h + 'px',
                               transform: `translate(-50%,-50%) translate(${off.x}px,${off.y}px) rotate(${rot}deg) scale(${scale})` }">
                <!-- circular mask: everything outside the circle is dimmed -->
                <div class="pointer-events-none absolute inset-0 rounded-full"
                     style="box-shadow: 0 0 0 999px rgb(16 21 23 / .55), inset 0 0 0 2px rgb(255 255 255 / .9)"></div>
            </div>
            <p class="hint mt-3 text-center">{{ coarse ? t('cropHintTouch') : t('cropHint') }}</p>

            <div class="mt-4 flex items-center gap-3">
                <span class="text-sm text-muted">{{ t('zoom') }}</span>
                <input type="range" min="1" max="4" step="0.01" :value="zoom" class="h-2 flex-1 accent-[var(--brand)]"
                       :aria-label="t('zoom')" @input="setZoom(+$event.target.value)">
                <button type="button" class="icon-btn" :title="t('rotate')" :aria-label="t('rotate')" @click="rotate"><Icon name="rotate" :size="20" /></button>
            </div>

            <div class="mt-5 flex items-center gap-2 sm:mt-6">
                <button type="button" class="btn btn-quiet !px-3" @click="reset">{{ t('reset') }}</button>
                <button type="button" class="btn btn-ghost !px-3 sm:ms-auto sm:!px-5" @click="emit('cancel')">{{ t('cancel') }}</button>
                <button type="button" class="btn btn-primary flex-1 !px-3 sm:flex-none sm:!px-5" :disabled="busy || !natural.w" @click="confirm">
                    <span class="sm:hidden">{{ t('save') }}</span><span class="hidden sm:inline">{{ t('setAsAvatar') }}</span>
                </button>
            </div>
        </div>
    </Modal>
</template>
