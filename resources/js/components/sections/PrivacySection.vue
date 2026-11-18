<script setup>
import { api } from '../../api';
import { state, setAccount, toast } from '../../store';
import { t } from '../../i18n';
import SearchPreview from './SearchPreview.vue';

const ITEMS = [
    ['show_avatar', 'showAvatar', 'showAvatarD'],
    ['show_profile', 'showProfile', 'showProfileD'],
    ['allow_indexing', 'allowIndexing', 'allowIndexingD'],
];

async function toggle(key, value) {
    const prev = state.draft[key];
    state.draft[key] = value;
    try {
        const draftBefore = JSON.parse(JSON.stringify(state.draft));
        const acc = await api('PATCH', '/account/privacy', { [key]: value });
        setAccount(acc);
        // keep unsaved edits from other sections
        Object.assign(state.draft, { ...draftBefore, [key]: acc.profile[key] });
        toast(t('saved'));
    } catch (e) {
        state.draft[key] = prev;
        throw e;
    }
}
</script>

<template>
    <div>
        <ul class="space-y-3">
            <li v-for="[key, label, desc] in ITEMS" :key="key" class="flex items-center justify-between gap-4 rounded-[8px] border border-line p-4">
                <div>
                    <div class="font-medium">{{ t(label) }}</div>
                    <div class="hint mt-0.5">{{ t(desc) }}</div>
                </div>
                <button type="button" role="switch" :aria-checked="state.draft[key]" :aria-label="t(label)"
                        class="relative h-6 w-11 shrink-0 rounded-full transition-colors"
                        :class="state.draft[key] ? 'bg-brand' : 'bg-line'" @click="toggle(key, !state.draft[key])">
                    <span class="absolute top-0.5 size-5 rounded-full bg-white shadow transition-all"
                          :class="state.draft[key] ? 'end-0.5' : 'start-0.5'"></span>
                </button>
            </li>
        </ul>
        <SearchPreview />
    </div>
</template>
