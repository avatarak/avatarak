<script setup>
import { ref, watch, nextTick, onBeforeUnmount } from 'vue';

/**
 * Accessible modal: animated in/out, closes on Esc or a genuine backdrop click, traps focus,
 * locks page scroll and restores focus. The panel is whatever you put in the default slot.
 */
const props = defineProps({ open: Boolean, label: String, width: { type: String, default: '28rem' } });
const emit = defineEmits(['close']);

const panel = ref(null);
let pressedOnBackdrop = false;
let opener = null;
let prevOverflow = '', prevPad = '';

// A click only counts as "outside" if the press STARTED on the backdrop too. Otherwise selecting text in
// a field and releasing the mouse outside the card would close the modal (the "closes by itself" bug).
const down = (e) => { pressedOnBackdrop = e.target === e.currentTarget; };
const click = (e) => { if (pressedOnBackdrop && e.target === e.currentTarget) emit('close'); pressedOnBackdrop = false; };

const FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),textarea:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])';
function keydown(e) {
    if (e.key === 'Escape') { e.stopPropagation(); emit('close'); return; }
    if (e.key !== 'Tab' || !panel.value) return;
    const items = [...panel.value.querySelectorAll(FOCUSABLE)].filter((el) => el.offsetParent !== null);
    if (!items.length) return;
    const [first, last] = [items[0], items[items.length - 1]];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
}

function lock() {
    prevOverflow = document.body.style.overflow;
    prevPad = document.body.style.paddingInlineEnd;
    const sb = window.innerWidth - document.documentElement.clientWidth;   // avoid layout jump
    document.body.style.overflow = 'hidden';
    if (sb > 0) document.body.style.paddingInlineEnd = sb + 'px';
}
function unlock() { document.body.style.overflow = prevOverflow; document.body.style.paddingInlineEnd = prevPad; }

watch(() => props.open, async (v) => {
    if (typeof document === 'undefined') return;      // build-time pre-rendering
    if (v) {
        opener = document.activeElement;
        lock();
        document.addEventListener('keydown', keydown, true);
        await nextTick();
        (panel.value?.querySelector('[data-autofocus]') || panel.value?.querySelector(FOCUSABLE))?.focus({ preventScroll: true });
    } else {
        document.removeEventListener('keydown', keydown, true);
        unlock();
        opener?.focus?.({ preventScroll: true });
    }
}, { immediate: true });

onBeforeUnmount(() => { document.removeEventListener('keydown', keydown, true); if (props.open) unlock(); });
</script>

<template>
    <Teleport to="body">
        <Transition name="modal" appear>
            <div v-if="open" class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-ink/45 p-4 backdrop-blur-[2px]"
                 @pointerdown="down" @click="click">
                <div ref="panel" class="modal-panel card my-auto w-full" :style="{ maxWidth: width }" role="dialog" aria-modal="true" :aria-label="label">
                    <slot />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
