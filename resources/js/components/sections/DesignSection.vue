<script setup>
import { computed } from 'vue';
import { state } from '../../store';
import { useSubmit } from '../../composables';
import { t } from '../../i18n';
import Icon from '../Icon.vue';
import SaveBar from '../SaveBar.vue';

const THEMES = [
    ['classic', '#1D4FC4'], ['mocha', '#A8561A'], ['mint', '#0A8F6E'], ['mustard', '#F2A900'],
    ['midnight', '#6D4AE8'], ['molten', '#E0360B'], ['earth', '#2E6B30'], ['cyber', '#D63AF2'],
];
const current = computed(() => (state.draft.accent_color || '#1D4FC4').toUpperCase());
const isCustom = computed(() => !THEMES.some(([, c]) => c === current.value));

state.draft.hidden_sections = [...(state.draft.hidden_sections || [])];
const hidden = (s) => state.draft.hidden_sections.includes(s);
function toggle(s) {
    const h = state.draft.hidden_sections;
    hidden(s) ? h.splice(h.indexOf(s), 1) : h.push(s);
}

const { busy, message, run, err } = useSubmit('PATCH', '/account/appearance', () => ({
    accent_color: state.draft.accent_color || null, hidden_sections: state.draft.hidden_sections,
}));
</script>

<template>
    <form @submit.prevent="run">
        <h3 class="text-lg font-bold">{{ t('colors') }}</h3>
        <div class="mt-3 grid grid-cols-3 gap-3">
            <button v-for="[name, color] in THEMES" :key="name" type="button"
                    class="flex h-[106px] flex-col items-center justify-center gap-3 rounded-[6px] border bg-white transition-colors"
                    :class="current === color && !isCustom ? 'border-ink ring-1 ring-ink' : 'border-line hover:border-muted'"
                    :aria-pressed="current === color" @click="state.draft.accent_color = color">
                <span class="flex" aria-hidden="true">
                    <span class="size-8 rounded-full border border-line" :style="{ background: `color-mix(in srgb, ${color} 18%, #fff)` }"></span>
                    <span class="-ms-3 size-8 rounded-full border border-line bg-white"></span>
                    <span class="-ms-3 size-8 rounded-full" :style="{ background: color }"></span>
                </span>
                <span class="text-sm">{{ t('th_' + name) }}</span>
            </button>
            <label class="relative flex h-[106px] cursor-pointer flex-col items-center justify-center gap-3 rounded-[6px] border bg-white transition-colors"
                   :class="isCustom ? 'border-ink ring-1 ring-ink' : 'border-line hover:border-muted'">
                <span class="flex" aria-hidden="true">
                    <span class="size-8 rounded-full border border-line bg-white"></span>
                    <span class="-ms-3 size-8 rounded-full border border-line bg-white"></span>
                    <span class="-ms-3 size-8 rounded-full border border-line" :style="isCustom ? { background: current } : null"></span>
                </span>
                <span class="text-sm">{{ t('th_custom') }}</span>
                <input type="color" class="absolute inset-0 size-full cursor-pointer opacity-0" :value="current" :aria-label="t('th_custom')"
                       @input="state.draft.accent_color = $event.target.value.toUpperCase()">
            </label>
        </div>
        <p v-if="err('accent_color')" class="mt-2 text-sm text-red-600">{{ err('accent_color') }}</p>

        <h3 class="mt-9 text-lg font-bold">{{ t('sections') }}</h3>
        <ul class="mt-3 space-y-2">
            <li class="flex items-center gap-3 rounded-[6px] bg-soft p-2.5">
                <span class="grid size-10 place-items-center rounded-[6px] bg-white"><Icon name="user" :size="20" /></span>
                <span class="flex-1">{{ t('sec_intro') }}</span>
                <span class="px-2 text-sm text-muted">{{ t('pinned') }}</span>
            </li>
            <li v-for="s in ['bio', 'links']" :key="s" class="flex items-center gap-3 rounded-[6px] bg-soft p-2.5" :class="{ 'opacity-60': hidden(s) }">
                <span class="grid size-10 place-items-center rounded-[6px] bg-white"><Icon :name="s === 'bio' ? 'user' : 'link'" :size="20" /></span>
                <span class="flex-1">{{ t('sec_' + s) }}</span>
                <button type="button" class="icon-btn" :aria-label="hidden(s) ? t('show') : t('hide')" :aria-pressed="!hidden(s)" @click="toggle(s)">
                    <Icon :name="hidden(s) ? 'eyeOff' : 'eye'" :size="20" />
                </button>
            </li>
        </ul>
        <SaveBar :busy="busy" :error="message" />
    </form>
</template>
