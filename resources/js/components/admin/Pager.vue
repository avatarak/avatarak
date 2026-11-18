<script setup>
import { t } from '../../i18n';
import { fmtNum } from '../../admin/lib';
import Icon from '../Icon.vue';

defineProps({ meta: { type: Object, required: true } });
defineEmits(['page']);
</script>

<template>
    <div v-if="meta.total > 0" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm text-muted">
        <span>{{ fmtNum(meta.total) }} {{ t('a_results') }}</span>
        <div v-if="meta.last_page > 1" class="flex items-center gap-1">
            <button type="button" class="icon-btn" :disabled="meta.page <= 1" :aria-label="t('a_prev')" @click="$emit('page', meta.page - 1)"><Icon name="chevron" :size="18" class="rotate-180 rtl:rotate-0" /></button>
            <span class="tabular-nums">{{ t('a_page_of') }} {{ fmtNum(meta.page) }} {{ t('a_of') }} {{ fmtNum(meta.last_page) }}</span>
            <button type="button" class="icon-btn" :disabled="meta.page >= meta.last_page" :aria-label="t('a_next')" @click="$emit('page', meta.page + 1)"><Icon name="chevron" :size="18" class="rtl:rotate-180" /></button>
        </div>
    </div>
</template>
