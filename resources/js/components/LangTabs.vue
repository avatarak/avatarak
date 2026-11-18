<script setup>
import { state } from '../store';
import { lang, editLang } from '../lang';
</script>

<template>
    <div v-if="state.draft.languages === 'both'" class="mb-5 inline-flex rounded-full bg-soft p-1 text-sm font-semibold" role="tablist" aria-label="Language">
        <button v-for="[l, label] in [['fa', 'فارسی'], ['en', 'English']]" :key="l" type="button" role="tab" :aria-selected="editLang() === l"
                class="rounded-full px-4 py-1.5 transition-colors" :class="editLang() === l ? 'bg-white text-ink shadow-card' : 'text-muted hover:text-ink'" @click="lang.tab = l">{{ label }}</button>
    </div>
</template>
