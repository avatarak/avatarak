<script setup>
import { ref, onMounted, watch } from 'vue';
import { t, i18n } from '../i18n';
import { siteStatus, loadStatus } from '../store';
import Icon from './Icon.vue';

const KEY = 'dismissed-announcements';
const closed = ref([]);
try { closed.value = JSON.parse(localStorage.getItem(KEY) || '[]'); } catch { /* storage may be blocked */ }
onMounted(loadStatus);
watch(() => i18n.locale, loadStatus);          // the text comes in the visitor's language

function dismiss(a) {
    closed.value = [...closed.value, a.id];
    try { localStorage.setItem(KEY, JSON.stringify(closed.value.slice(-20))); } catch { /* ignore */ }
}
const shown = (a) => !a.dismissible || !closed.value.includes(a.id);
const TONE = { info: 'bg-blue-50 text-blue-900 border-blue-200', success: 'bg-emerald-50 text-emerald-900 border-emerald-200', warning: 'bg-amber-50 text-amber-900 border-amber-200' };
</script>

<template>
    <div v-if="siteStatus.announcements.some(shown)">
        <template v-for="a in siteStatus.announcements" :key="a.id">
            <div v-if="shown(a)" class="flex items-start gap-3 border-b px-4 py-2.5 text-sm" :class="TONE[a.level] || TONE.info" role="status">
                <p class="min-w-0 flex-1 whitespace-pre-line leading-6">{{ a.message }}</p>
                <button v-if="a.dismissible" type="button" class="icon-btn !size-7 shrink-0" :aria-label="t('close')" @click="dismiss(a)"><Icon name="close" :size="16" /></button>
            </div>
        </template>
    </div>
</template>
