<script setup>
import { ref, watch, computed } from 'vue';
import { t } from '../../i18n';
import Modal from '../Modal.vue';

/**
 * One dialog for every admin action.
 *  - reasonLabel: shows a required text box (min 3 chars), sent back as {reason}
 *  - noteLabel:   shows an optional note, sent back as {note}
 *  - typed:       the user must type exactly this text before the button enables
 *  - checks:      [{key,label}] extra checkboxes, sent back as {checks:{key:bool}}
 */
const props = defineProps({
    open: Boolean, title: String, body: String, confirmLabel: String, danger: Boolean, busy: Boolean, error: String,
    reasonLabel: String, noteLabel: String, typed: String, typedLabel: String, checks: { type: Array, default: () => [] },
    inputLabel: String, inputValue: { type: String, default: '' }, inputDir: { type: String, default: 'ltr' }, inputType: { type: String, default: 'text' },
});
const emit = defineEmits(['close', 'confirm']);

const reason = ref(''); const note = ref(''); const typedIn = ref(''); const input = ref(''); const picked = ref({});
watch(() => props.open, (v) => { if (v) { reason.value = ''; note.value = ''; typedIn.value = ''; input.value = props.inputValue; picked.value = {}; } });

const ok = computed(() => (!props.reasonLabel || reason.value.trim().length >= 3)
    && (!props.typed || typedIn.value.trim().toLowerCase() === props.typed.toLowerCase())
    && (!props.inputLabel || String(input.value).trim() !== ''));
const submit = () => ok.value && !props.busy && emit('confirm', { reason: reason.value.trim(), note: note.value.trim(), checks: { ...picked.value }, input: String(input.value).trim() });
</script>

<template>
    <Modal :open="open" :label="title" width="30rem" @close="emit('close')">
        <form class="p-6" novalidate @submit.prevent="submit">
            <h2 class="text-xl font-bold" :class="danger ? 'text-red-700' : ''">{{ title }}</h2>
            <p v-if="body" class="mt-2 text-sm leading-6 text-ink-2">{{ body }}</p>

            <div class="mt-4 space-y-4">
                <div v-if="reasonLabel">
                    <label class="label" for="cd-reason">{{ reasonLabel }}</label>
                    <textarea id="cd-reason" v-model="reason" rows="2" maxlength="255" class="field" data-autofocus></textarea>
                </div>
                <div v-if="inputLabel">
                    <label class="label" for="cd-input">{{ inputLabel }}</label>
                    <input id="cd-input" v-model="input" :type="inputType" :dir="inputDir" class="field text-start" data-autofocus autocomplete="off" spellcheck="false">
                </div>
                <label v-for="c in checks" :key="c.key" class="flex items-start gap-2.5 text-sm">
                    <input v-model="picked[c.key]" type="checkbox" class="mt-1.5 size-4 accent-[var(--brand)]">
                    <span>{{ c.label }}</span>
                </label>
                <div v-if="noteLabel">
                    <label class="label" for="cd-note">{{ noteLabel }}</label>
                    <textarea id="cd-note" v-model="note" rows="2" maxlength="500" class="field"></textarea>
                </div>
                <div v-if="typed">
                    <label class="label" for="cd-typed">{{ typedLabel }} <bdi dir="ltr" class="font-mono text-xs">{{ typed }}</bdi></label>
                    <input id="cd-typed" v-model="typedIn" dir="ltr" class="field text-start" autocomplete="off" spellcheck="false" data-autofocus>
                </div>
            </div>

            <p v-if="error" class="mt-3 text-sm text-red-600" role="alert">{{ error }}</p>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" class="btn btn-ghost" @click="emit('close')">{{ t('a_cancel') }}</button>
                <button class="btn" :class="danger ? '!bg-red-600 text-white hover:!bg-red-700' : 'btn-primary'" :disabled="!ok || busy">{{ confirmLabel || t('a_confirm') }}</button>
            </div>
        </form>
    </Modal>
</template>
