<script setup>
import { computed } from 'vue';
import { state } from '../../store';
import { useSubmit } from '../../composables';
import { t } from '../../i18n';
import SaveBar from '../SaveBar.vue';

const PRESETS = ['#3563E9', '#0F9D7A', '#D9480F', '#7048E8', '#C2255C', '#20242A'];
const order = computed({
    get: () => [...new Set([...(state.draft.section_order || []), 'bio', 'links', 'website'])],
    set: (v) => (state.draft.section_order = v),
});

function move(i, d) {
    const a = [...order.value];
    const j = i + d;
    if (j < 0 || j >= a.length) return;
    [a[i], a[j]] = [a[j], a[i]];
    order.value = a;
}

const { busy, message, run, err } = useSubmit('PATCH', '/account/appearance', () => ({
    accent_color: state.draft.accent_color || null, section_order: order.value,
}));
</script>

<template>
    <form @submit.prevent="run">
        <h2 class="font-display text-2xl font-bold">{{ t('appearanceTitle') }}</h2>

        <fieldset class="mt-6">
            <legend class="label">{{ t('accent') }}</legend>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn btn-ghost !py-1.5 text-sm" :class="{ 'ring-2 ring-brand': !state.draft.accent_color }"
                        @click="state.draft.accent_color = null">{{ t('defaultColor') }}</button>
                <button v-for="c in PRESETS" :key="c" type="button" class="size-9 rounded-full border border-line"
                        :class="{ 'ring-2 ring-offset-2 ring-ink': state.draft.accent_color === c }"
                        :style="{ background: c }" :aria-label="c" @click="state.draft.accent_color = c"></button>
                <input type="color" class="size-9 cursor-pointer rounded-full border border-line bg-surface p-0.5"
                       :value="state.draft.accent_color || '#3563E9'" aria-label="custom color"
                       @input="state.draft.accent_color = $event.target.value.toUpperCase()">
            </div>
            <p v-if="err('accent_color')" class="mt-1 text-sm text-red-600">{{ err('accent_color') }}</p>
        </fieldset>

        <fieldset class="mt-8">
            <legend class="label">{{ t('sectionOrder') }}</legend>
            <ol class="space-y-2">
                <li v-for="(s, i) in order" :key="s" class="card flex items-center justify-between px-4 py-2.5">
                    <span>{{ t('sec_' + s) }}</span>
                    <span class="flex gap-1">
                        <button type="button" class="btn btn-ghost !px-2.5 !py-1" :disabled="i === 0" :aria-label="t('up')" @click="move(i, -1)">↑</button>
                        <button type="button" class="btn btn-ghost !px-2.5 !py-1" :disabled="i === order.length - 1" :aria-label="t('down')" @click="move(i, 1)">↓</button>
                    </span>
                </li>
            </ol>
        </fieldset>
        <SaveBar :busy="busy" :error="message" />
    </form>
</template>
